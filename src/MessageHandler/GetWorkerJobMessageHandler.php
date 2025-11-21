<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\WorkerStateRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWorkerJobMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private WorkerClientFactory $workerClientFactory,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor);
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerNotReadyException
     */
    public function __invoke(GetWorkerJobMessage $message): void
    {
        $this->assessReadiness($message);

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $this->eventDispatcher->dispatch(new WorkerStateRetrievedEvent(
                $message->getJobId(),
                $message->machineIpAddress,
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
