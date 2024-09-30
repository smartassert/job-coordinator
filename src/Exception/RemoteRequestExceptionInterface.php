<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Message\JobRemoteRequestMessageInterface;

interface RemoteRequestExceptionInterface extends \Throwable
{
    public function getJob(): Job;

    public function getPreviousException(): \Throwable;

    public function getFailedMessage(): JobRemoteRequestMessageInterface;
}
