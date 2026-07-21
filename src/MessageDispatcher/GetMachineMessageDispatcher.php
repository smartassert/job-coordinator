<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobEventInterface as JobEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineEventInterface as MachineEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use webignition\SymfonyMessengerDelayMiddleware\NonDelayedStamp;

readonly class GetMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
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
    private function doDispatch(JobEvent&MachineEvent $event, array $stamps = []): void
    {
        $message = new GetMachineMessage($event->getJobId(), $event->getMachine());

        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message, $stamps);
    }
}
