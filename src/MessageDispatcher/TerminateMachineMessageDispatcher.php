<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Message\TerminateMachineMessage;
use App\MessageDispatcher\AbstractRedispatchingMessageDispatcher as BaseMessageDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class TerminateMachineMessageDispatcher extends BaseMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobStateRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(ResultsJobStateRetrievedEvent $event): void
    {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(new TerminateMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
        ));
    }

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof TerminateMachineMessage;
    }
}
