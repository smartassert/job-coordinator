<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\WorkerJobRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
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
        private AuthenticationTokenProvider $authenticationTokenProvider,
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

        $workerClient = $this->workerClientFactory->create($message->machineIpAddress);

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        try {
            $this->eventDispatcher->dispatch(new WorkerJobRetrievedEvent(
                $authenticationToken,
                $message->getJobId(),
                $message->machineIpAddress,
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
