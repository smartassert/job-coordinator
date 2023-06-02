<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class WorkerJobStartException extends AbstractRemoteRequestException
{
    public function __construct(Job $job, \Throwable $previousException)
    {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to start job "%s": %s',
                $job->id,
                $previousException->getMessage()
            ),
        );
    }
}
