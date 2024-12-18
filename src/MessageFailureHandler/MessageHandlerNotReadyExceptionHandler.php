<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\MessageHandlerNotReadyException;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\Messenger\Envelope;

readonly class MessageHandlerNotReadyExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Envelope $envelope, \Throwable $throwable): void
    {
        if (!$throwable instanceof MessageHandlerNotReadyException) {
            return;
        }

        if (MessageHandlingReadiness::NEVER === $throwable->getReadiness()) {
            $this->eventDispatcher->dispatch(
                new MessageNotHandleableEvent($throwable->getHandlerMessage())
            );
        }

        $this->eventDispatcher->dispatch(
            new MessageNotYetHandleableEvent($throwable->getHandlerMessage())
        );
    }
}
