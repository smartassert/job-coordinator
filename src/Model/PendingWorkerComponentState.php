<?php

declare(strict_types=1);

namespace App\Model;

class PendingWorkerComponentState implements WorkerComponentStateInterface
{
    public function toArray(): array
    {
        return [
            'state' => 'pending',
            'meta_state' => $this->getMetaState()->jsonSerialize(),
        ];
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(false, false);
    }
}
