<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Message\MachineStateChangeCheckMessage;
use Symfony\Component\Messenger\Envelope;

class MachineStateChangeCheckMessageDispatcher extends AbstractDeferredMessageDispatcher
{
    public function dispatch(MachineStateChangeCheckMessage $message): Envelope
    {
        return $this->doDispatch($message);
    }
}
