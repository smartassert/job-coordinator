<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

readonly class MessageStateMutator
{
    public function __construct(
        private UnhandleableMessageHandler $unhandleableMessageHandler,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function set(
        JobRemoteRequestMessageInterface $message,
        MessageHandlingReadiness $readiness
    ): void {
        if (MessageHandlingReadiness::NOW === $readiness) {
            $message->setState(MessageState::HANDLING);
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
            $message->setState(MessageState::HALTED);
        }

        if (MessageHandlingReadiness::NEVER === $readiness) {
            $message->setState(MessageState::STOPPED);
        }

        if (MessageHandlingReadiness::NOW !== $readiness) {
            $this->unhandleableMessageHandler->handle($message, $readiness);
        }
    }
}
