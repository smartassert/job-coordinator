<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;

interface RemoteRequestExceptionInterface extends \Throwable
{
    /**
     * @return non-empty-string
     */
    public function getJobId(): string;

    public function getPreviousException(): \Throwable;

    public function getFailedMessage(): JobRemoteRequestMessageInterface;
}
