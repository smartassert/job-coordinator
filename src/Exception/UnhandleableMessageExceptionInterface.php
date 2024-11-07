<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;

interface UnhandleableMessageExceptionInterface extends \Throwable
{
    public function getFailedMessage(): JobRemoteRequestMessageInterface;
}
