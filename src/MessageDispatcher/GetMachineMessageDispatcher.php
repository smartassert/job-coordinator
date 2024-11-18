<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreationRequestedEvent::class => [
                ['dispatch', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatchIfMachineNotInEndState', 100],
            ],
        ];
    }

    public function dispatchIfMachineNotInEndState(MachineRetrievedEvent $event): void
    {
        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(new GetMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getMachine()
        ));
    }

    public function dispatch(MachineCreationRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetMachineMessage($event->getAuthenticationToken(), $event->getJobId(), $event->getMachine())
        );
    }
}
