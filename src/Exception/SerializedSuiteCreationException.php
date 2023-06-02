<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class SerializedSuiteCreationException extends AbstractRemoteRequestException
{
    public function __construct(Job $job, \Throwable $previousException)
    {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to create serialized suite for job "%s": %s',
                $job->id,
                $previousException->getMessage()
            )
        );
    }
}
