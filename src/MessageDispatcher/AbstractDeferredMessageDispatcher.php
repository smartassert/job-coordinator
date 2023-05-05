<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class AbstractDeferredMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function doDispatch(object $message): Envelope
    {
        return $this->messageBus->dispatch(
            new Envelope($message)
        );
    }
}
