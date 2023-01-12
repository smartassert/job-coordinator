<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeEvent extends Event
{
    public function __construct(
        public readonly ?string $previous,
        public readonly string $current,
    ) {
    }
}
