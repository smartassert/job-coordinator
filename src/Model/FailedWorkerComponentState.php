<?php

declare(strict_types=1);

namespace App\Model;

class FailedWorkerComponentState implements WorkerComponentStateInterface
{
    public function toArray(): array
    {
        return [
            'state' => 'failed',
            'meta_state' => $this->getMetaState()->jsonSerialize(),
        ];
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(true, false);
    }
}
