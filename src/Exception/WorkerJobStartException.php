<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;

class WorkerJobStartException extends \Exception
{
    public function __construct(
        public readonly Job $job,
        public readonly \Throwable $previousException
    ) {
        parent::__construct(
            sprintf(
                'Failed to start job "%s": %s',
                $job->id,
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }
}
