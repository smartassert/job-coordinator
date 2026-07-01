<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\WorkerJobRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class GetWorkerJobMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private WorkerClientFactory $workerClientFactory,
        EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $messageBus, $logger);
    }

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetWorkerJobMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->setMessageState($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $workerClient = $this->workerClientFactory->create($message->machineIpAddress);

        try {
            $this->eventDispatcher->dispatch(new WorkerJobRetrievedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $message->machineIpAddress,
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
