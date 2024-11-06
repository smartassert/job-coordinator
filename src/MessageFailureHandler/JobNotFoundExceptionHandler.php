<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Event\MessageNotHandleableEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\Messenger\Envelope;

readonly class JobNotFoundExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(Envelope $envelope, \Throwable $throwable): void
    {
        if (!$throwable instanceof MessageHandlerJobNotFoundException) {
            return;
        }

        $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($throwable->handledMessage));
    }
}
