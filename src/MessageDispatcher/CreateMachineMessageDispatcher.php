<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\RequestState;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
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

        $this->messageBus->dispatch(new Envelope(
            new CreateMachineMessage($event->authenticationToken, $event->jobId, 0),
            [new NonDelayedStamp()]
        ));
    }
}
