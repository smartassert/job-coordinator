<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsReadyEvent;
use App\Message\IsWorkerReadyMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Services\UnhandleableMessageHandler;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class IsWorkerReadyMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private UnhandleableMessageHandler $unhandleableMessageHandler,
        private WorkerClientFactory $workerClientFactory,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private AuthenticationTokenProvider $authenticationTokenProvider,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(IsWorkerReadyMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $workerClient = $this->workerClientFactory->create($message->machineIpAddress);

        $isReady = $workerClient->isReady();
        $this->logger->info(sprintf(
            '%s isReady for "%s": %s',
            $message::class,
            $message->getJobId(),
            $isReady ? 'is ready' : 'is not ready'
        ));

        if (!$isReady) {
            $this->unhandleableMessageHandler->handle($message, MessageHandlingReadiness::EVENTUALLY);

            return;
        }

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new MachineIsReadyEvent(
                $message->getJobId(),
                $message->machineIpAddress
            )
        );
    }
}
