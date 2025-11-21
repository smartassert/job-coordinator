<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\MachineIpAddressInterface;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetWorkerJobMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            WorkerStateRetrievedEvent::class => [
                ['dispatchImmediately', 100],
            ],
        ];
    }

    public function dispatchImmediately(JobEventInterface&MachineIpAddressInterface $event): void
    {
        $message = new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress());
        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }
}
