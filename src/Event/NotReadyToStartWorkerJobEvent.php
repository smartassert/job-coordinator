<?php

declare(strict_types=1);

namespace App\Event;

use App\Message\CreateWorkerJobMessage;
use Symfony\Contracts\EventDispatcher\Event;

class NotReadyToStartWorkerJobEvent extends Event
{
    public function __construct(
        public readonly CreateWorkerJobMessage $message,
    ) {
    }
}
