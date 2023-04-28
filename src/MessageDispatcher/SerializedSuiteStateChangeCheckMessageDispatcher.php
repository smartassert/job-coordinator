<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\GetSerializedSuiteStateMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class SerializedSuiteStateChangeCheckMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {
    }

    public function dispatch(GetSerializedSuiteStateMessage $message): Envelope
    {
        return $this->messageBus->dispatch(
            new Envelope($message, [new DelayStamp($this->dispatchDelay)])
        );
    }
}
