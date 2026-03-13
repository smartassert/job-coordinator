<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class GetMachineMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private WorkerManagerClient $workerManagerClient,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor, $messageBus, $logger);
    }

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $readiness = $this->assessReadiness($message);
        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $previousMachine = $message->machine;

        try {
            $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
                $message->authenticationToken,
                $previousMachine,
                $machine
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
