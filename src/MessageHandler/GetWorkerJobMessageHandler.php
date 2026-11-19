<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\WorkerJobRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Services\MessageStateMutator;
use App\Services\UserSecurityTokenProvider;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class GetWorkerJobMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private WorkerClientFactory $workerClientFactory,
        private EventDispatcherInterface $eventDispatcher,
        private UserSecurityTokenProvider $userSecurityTokenProvider,
        private MachineRepository $machineRepository,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetWorkerJobMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $userSecurityToken = $this->userSecurityTokenProvider->get($message->getJobId());
        $machine = $this->machineRepository->find($message->getJobId());
        $ipAddress = $machine?->getIpAddress();
        if (
            null === $userSecurityToken
            || null === $machine
            || null === $ipAddress
        ) {
            return;
        }

        $workerClient = $this->workerClientFactory->create($ipAddress);

        try {
            $this->eventDispatcher->dispatch(new WorkerJobRetrievedEvent(
                $message->getJobId(),
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
