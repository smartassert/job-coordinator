<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use SmartAssert\WorkerManagerClient\Model\MachineInterface;

class MachineRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(
        Job $job,
        public readonly MachineInterface $machine,
        \Throwable $previousException
    ) {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to get worker machine "%s": %s',
                $this->machine->getId(),
                $previousException->getMessage()
            ),
        );
    }
}
