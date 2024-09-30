<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Message\JobRemoteRequestMessageInterface;

class SerializedSuiteRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(
        Job $job,
        SerializedSuite $serializedSuite,
        \Throwable $previousException,
        JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to retrieve serialized suite "%s": %s',
                $serializedSuite->getId(),
                $previousException->getMessage()
            ),
            $failedMessage,
        );
    }
}
