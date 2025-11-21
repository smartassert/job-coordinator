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
                ['dispatchImmediately', 100],
            ],
        ];
    }

    public function dispatchImmediately(ResultsJobStateRetrievedEvent $event): void
    {
        $message = new TerminateMachineMessage($event->getAuthenticationToken(), $event->getJobId());
        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof TerminateMachineMessage;
    }
}
