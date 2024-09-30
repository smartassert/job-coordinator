<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Message\JobRemoteRequestMessageInterface;

class WorkerStateRetrievalException extends AbstractRemoteRequestException
{
    public function __construct(
        Job $job,
        \Throwable $previousException,
        JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct(
            $job,
            $previousException,
            sprintf(
                'Failed to retrieve worker state "%s": %s',
                $job->id,
                $previousException->getMessage()
            ),
            $failedMessage,
        );
    }
}
