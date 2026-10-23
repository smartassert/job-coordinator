<?php

declare(strict_types=1);

namespace App\Model\Machine;

use SmartAssert\WorkerManagerClient\Model\ActionFailure as RemoteActionFailure;

final readonly class WorkerManagerActionFailure implements ActionFailureInterface
{
    public function __construct(
        private RemoteActionFailure $workerManagerActionFailure,
    ) {}

    public function getAction(): string
    {
        return $this->workerManagerActionFailure->action;
    }

    public function getType(): string
    {
        return $this->workerManagerActionFailure->type;
    }

    public function getContext(): array
    {
        return $this->workerManagerActionFailure->context;
    }
}
