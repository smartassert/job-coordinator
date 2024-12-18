<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetMachineMessageHandler extends AbstractMessageHandler
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
     * @throws MessageHandlerNotReadyException
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $this->assessReadiness($message);

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
