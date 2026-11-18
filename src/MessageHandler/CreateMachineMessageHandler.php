<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteEventName;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\MessageStateMutator;
use App\Services\NotifyUrlFactory;
use App\Services\UserSecurityTokenProvider;
use App\Services\WorkerManagerClientWrapper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateMachineMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private WorkerManagerClientWrapper $workerManagerClientWrapper,
        private EventDispatcherInterface $eventDispatcher,
        private UserSecurityTokenProvider $userSecurityTokenProvider,
        private NotifyUrlFactory $notifyUrlFactory,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $jobId = $message->getJobId();

        $readiness = $this->readinessAssessor->isReady($jobId);
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $notifyUrl = $this->notifyUrlFactory->create(RemoteEventName::MACHINE_STATE_CHANGED);
        $userSecurityToken = $this->userSecurityTokenProvider->get($jobId);
        if (null === $notifyUrl || null === $userSecurityToken) {
            return;
        }

        try {
            $machine = $this->workerManagerClientWrapper->create($userSecurityToken, $jobId, $notifyUrl);

            $this->eventDispatcher->dispatch(
                new MachineCreationRequestedEvent($machine)
            );
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
