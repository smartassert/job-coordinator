<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Event\MessageNotHandleableEvent;
use App\Exception\UnhandleableMessageExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\Messenger\Envelope;

readonly class UnhandleableMessageExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Envelope $envelope, \Throwable $throwable): void
    {
        if (!$throwable instanceof UnhandleableMessageExceptionInterface) {
            return;
        }

        $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($throwable->getFailedMessage()));
    }
}
