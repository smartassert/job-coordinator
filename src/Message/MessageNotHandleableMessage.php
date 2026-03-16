<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MessageHandlingReadiness;

readonly class MessageNotHandleableMessage
{
    public function __construct(
        public JobRemoteRequestMessageInterface $message,
        public MessageHandlingReadiness $readiness,
    ) {}
}
