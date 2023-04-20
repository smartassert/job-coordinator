<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerMachine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MachineStateChangeCheckMessageHandler
{
    public function __construct(
        private readonly MachineStateChangeCheckMessageDispatcher $messageDispatcher,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws NonSuccessResponseException
     * @throws InvalidResponseTypeException
     */
    public function __invoke(MachineStateChangeCheckMessage $message): void
    {
        $previousMachine = $message->machine;

        $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $previousMachine->id);

        if ($previousMachine->state !== $machine->state) {
            $this->eventDispatcher->dispatch(
                $this->createMachineStateChangeEvent($message, $previousMachine, $machine)
            );
        }

        if ('end' !== $machine->stateCategory) {
            $this->messageDispatcher->dispatch(new MachineStateChangeCheckMessage(
                $message->authenticationToken,
                $machine
            ));
        }
    }

    private function createMachineStateChangeEvent(
        MachineStateChangeCheckMessage $message,
        WorkerManagerMachine $previousMachine,
        WorkerManagerMachine $machine,
    ): MachineStateChangeEvent {
        if ('active' === $machine->stateCategory) {
            return new MachineIsActiveEvent($message->authenticationToken, $previousMachine, $machine);
        }

        return new MachineStateChangeEvent($message->authenticationToken, $previousMachine, $machine);
    }
}
