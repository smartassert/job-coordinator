<?php

declare(strict_types=1);

namespace App\Event;

trait GetJobIdTrait
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }
}
