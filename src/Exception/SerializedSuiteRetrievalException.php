<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class SerializedSuiteRetrievalException extends \Exception
{
    public function __construct(
        public readonly Job $job,
        public readonly \Throwable $previousException
    ) {
        parent::__construct(
            sprintf(
                'Failed to retrieve serialized suite "%s": %s',
                $job->getSerializedSuiteId() ?? '',
                $this->previousException->getMessage()
            ),
            0,
            $previousException
        );
    }
}
