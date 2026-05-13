<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageDispatcher\AbstractMessageDispatcher as BaseMessageDispatcher;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateSerializedSuiteMessageDispatcher extends BaseMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        JobRemoteRequestMessageDispatcher $messageDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($messageDispatcher, $readinessAssessor);
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobCreatedEvent::class => [
                ['dispatchImmediately', 100],
            ],
        ];
    }

    public function dispatchImmediately(JobCreatedEvent $event): void
    {
        $message = new CreateSerializedSuiteMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getSuiteId(),
            $event->parameters
        );

        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }
}
