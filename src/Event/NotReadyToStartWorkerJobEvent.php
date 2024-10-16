<?php

declare(strict_types=1);

namespace App\Event;

use App\Message\StartWorkerJobMessage;
use Symfony\Contracts\EventDispatcher\Event;

class NotReadyToStartWorkerJobEvent extends Event
{
    public function __construct(
        public readonly StartWorkerJobMessage $message,
    ) {
    }
}
