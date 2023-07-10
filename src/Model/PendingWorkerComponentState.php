<?php

declare(strict_types=1);

namespace App\Model;

class PendingWorkerComponentState implements WorkerComponentStateInterface
{
    public function toArray(): array
    {
        return [
            'state' => 'pending',
            'is_end_state' => false,
        ];
    }
}
