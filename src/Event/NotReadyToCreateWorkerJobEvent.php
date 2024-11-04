<?php

declare(strict_types=1);

namespace App\Event;

use App\Message\CreateWorkerJobMessage;
use Symfony\Contracts\EventDispatcher\Event;

class NotReadyToCreateWorkerJobEvent extends Event
{
    public function __construct(
        public readonly CreateWorkerJobMessage $message,
    ) {
    }
}
