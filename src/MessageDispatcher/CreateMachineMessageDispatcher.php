<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                ['dispatch', 100],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (
            null === $job
            || RequestState::SUCCEEDED !== $job->getResultsJobRequestState()
            || 'prepared' !== $job->getSerializedSuiteState()
        ) {
            return;
        }

        $message = new CreateMachineMessage($event->authenticationToken, $event->jobId);
        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));

        $this->messageBus->dispatch(new Envelope($message, [new NonDelayedStamp()]));
    }
}
