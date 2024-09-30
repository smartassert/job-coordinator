<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Message\JobRemoteRequestMessageInterface;
use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(
        Job $job,
        public readonly Machine $machine,
        \Throwable $previousException,
        JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to get worker machine "%s": %s',
                $this->machine->id,
                $previousException->getMessage()
            ),
            $failedMessage,
        );
    }
}
