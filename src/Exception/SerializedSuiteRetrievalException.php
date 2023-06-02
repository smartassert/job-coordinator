<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class SerializedSuiteRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(Job $job, \Throwable $previousException)
    {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to retrieve serialized suite "%s": %s',
                $job->getSerializedSuiteId() ?? '',
                $previousException->getMessage()
            ),
        );
    }
}
