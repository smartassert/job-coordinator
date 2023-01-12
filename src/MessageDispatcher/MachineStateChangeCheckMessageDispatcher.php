<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\MachineStateChangeCheckMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class MachineStateChangeCheckMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {
    }

    public function dispatch(MachineStateChangeCheckMessage $message): Envelope
    {
        return $this->messageBus->dispatch(
            new Envelope(
                new MachineStateChangeCheckMessage(
                    $message->authenticationToken,
                    $message->machineId,
                    $message->currentState
                ),
                [new DelayStamp($this->dispatchDelay)]
            )
        );
    }
}
