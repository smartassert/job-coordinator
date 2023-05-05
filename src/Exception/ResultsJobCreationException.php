<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class ResultsJobCreationException extends \Exception
{
    public function __construct(
        public readonly Job $job,
        public readonly \Throwable $previousException
    ) {
        parent::__construct(
            sprintf(
                'Failed to create results job "%s": %s',
                $job->id,
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }
}
