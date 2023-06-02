<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class ResultsJobCreationException extends AbstractRemoteRequestException
{
    public function __construct(Job $job, \Throwable $previousException)
    {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to create results job "%s": %s',
                $job->id,
                $previousException->getMessage()
            ),
        );
    }
}
