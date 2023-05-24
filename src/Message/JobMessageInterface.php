<?php

declare(strict_types=1);

namespace App\Message;

interface JobMessageInterface
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string;
}
