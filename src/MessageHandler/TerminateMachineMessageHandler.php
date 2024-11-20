<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineTerminationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TerminateMachineMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private WorkerManagerClient $workerManagerClient,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor);
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(TerminateMachineMessage $message): void
    {
        if (!$this->isReady($message)) {
            return;
        }

        try {
            $machine = $this->workerManagerClient->deleteMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new MachineTerminationRequestedEvent($message->getJobId(), $machine));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
