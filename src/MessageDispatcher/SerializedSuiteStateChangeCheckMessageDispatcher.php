<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\GetSerializedSuiteStateMessage;
use Symfony\Component\Messenger\Envelope;

class SerializedSuiteStateChangeCheckMessageDispatcher extends AbstractDeferredMessageDispatcher
{
    public function dispatch(GetSerializedSuiteStateMessage $message): Envelope
    {
        return $this->doDispatch($message);
    }
}
