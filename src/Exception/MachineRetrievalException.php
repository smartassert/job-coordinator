<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineRetrievalException extends \Exception
{
    public function __construct(
        public readonly Job $job,
        public readonly Machine $machine,
        public readonly \Throwable $previousException
    ) {
        parent::__construct(
            sprintf(
                'Failed to get worker machine "%s": %s',
                $this->machine->id,
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }
}
