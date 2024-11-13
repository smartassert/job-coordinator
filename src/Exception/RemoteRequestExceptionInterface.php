<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;
use App\Model\JobInterface;

interface RemoteRequestExceptionInterface extends \Throwable
{
    public function getJob(): JobInterface;

    public function getPreviousException(): \Throwable;

    public function getFailedMessage(): JobRemoteRequestMessageInterface;
}
