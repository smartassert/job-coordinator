<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Message\MessageNotHandleableMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class MessageNotHandleableMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(MessageNotHandleableMessage $message): void
    {
        $this->eventDispatcher->dispatch(
            new MessageNotHandleableEvent($message->message, $message->readiness)
        );
    }
}
