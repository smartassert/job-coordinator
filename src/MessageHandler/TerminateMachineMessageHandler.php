<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineTerminationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class TerminateMachineMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private WorkerManagerClient $workerManagerClient,
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
    public function __invoke(TerminateMachineMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message);
        $this->setMessageState($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        try {
            $machine = $this->workerManagerClient->deleteMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new MachineTerminationRequestedEvent($message->getJobId(), $machine));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
