<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class MachineStateChangeCheckMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
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

        if (
            in_array($previousMachine->stateCategory, ['unknown', 'finding', 'pre_active'])
            && 'active' === $machine->stateCategory
        ) {
            $primaryIpAddress = $machine->ipAddresses[0] ?? null;
            if (!is_string($primaryIpAddress)) {
                return;
            }

            $this->eventDispatcher->dispatch(new MachineIsActiveEvent(
                $message->authenticationToken,
                $machine->id,
                $primaryIpAddress
            ));
        }

        if ($previousMachine->state !== $machine->state) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent(
                $message->authenticationToken,
                $previousMachine,
                $machine
            ));
        }

        if ('end' !== $machine->stateCategory) {
            $this->messageBus->dispatch(new MachineStateChangeCheckMessage($message->authenticationToken, $machine));
        }
    }
}
