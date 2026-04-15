<?php

declare(strict_types=1);

namespace App\Model;

class PendingWorkerComponentState implements WorkerComponentStateInterface
{
    public function getState(): string
    {
        return 'pending';
    }

    public function jsonSerialize(): array
    {
        return [
            'state' => 'pending',
            'meta_state' => $this->getMetaState(),
        ];
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(false, false);
    }
}
