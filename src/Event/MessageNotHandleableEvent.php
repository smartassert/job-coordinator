<?php

declare(strict_types=1);

namespace App\Event;

use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MessageNotHandleableEvent extends Event
{
    public function __construct(
        public readonly JobRemoteRequestMessageInterface $message,
    ) {
    }
}
