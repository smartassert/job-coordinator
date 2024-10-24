<?php

declare(strict_types=1);

namespace App\Event;

interface JobEventInterface
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string;
}
