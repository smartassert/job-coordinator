<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Entity\SerializedSuite;

class SerializedSuiteRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(Job $job, SerializedSuite $serializedSuite, \Throwable $previousException)
    {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to retrieve serialized suite "%s": %s',
                $serializedSuite->getId(),
                $previousException->getMessage()
            ),
        );
    }
}
