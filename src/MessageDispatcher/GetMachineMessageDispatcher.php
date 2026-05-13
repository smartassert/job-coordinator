<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use App\Event\JobEventInterface as JobEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineEventInterface as MachineEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\Messenger\NonDelayedStamp;
use App\ReadinessAssessor\ReadinessHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class GetMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessHandlerInterface $readinessAssessor,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreationRequestedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(MachineRetrievedEvent $event): void
    {
        $this->doDispatch($event);
    }

    public function dispatchImmediately(MachineCreationRequestedEvent $event): void
    {
        $this->doDispatch($event, [new NonDelayedStamp()]);
    }

    /**
     * @param StampInterface[] $stamps
     */
    private function doDispatch(AuthenticatingEvent&JobEvent&MachineEvent $event, array $stamps = []): void
    {
        $message = new GetMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getMachine()
        );

        $readiness = $this->readinessAssessor->isReady($message);
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message, $stamps);
    }
}
