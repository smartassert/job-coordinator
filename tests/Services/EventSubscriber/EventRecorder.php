<?php

declare(strict_types=1);

namespace App\Tests\Services\EventSubscriber;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\FooEvent;
use App\Event\JobCreatedEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Event\WorkerStateRetrievedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventRecorder implements EventSubscriberInterface, \Countable
{
    /**
     * @var Event[]
     */
    private array $events = [];

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangeEvent::class => [
                ['addEvent', 1000],
            ],
            MachineIsActiveEvent::class => [
                ['addEvent', 1000],
            ],
            JobCreatedEvent::class => [
                ['addEvent', 1000],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineCreationRequestedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineTerminationRequestedEvent::class => [
                ['addEvent', 1000],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            ResultsJobCreatedEvent::class => [
                ['addEvent', 1000],
            ],
            SerializedSuiteCreatedEvent::class => [
                ['addEvent', 1000],
            ],
            CreateWorkerJobRequestedEvent::class => [
                ['addEvent', 1000],
            ],
            WorkerStateRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineHasActionFailureEvent::class => [
                ['addEvent', 1000],
            ],
            FooEvent::class => [
                ['addEvent', 1000],
            ],
        ];
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getLatest(): ?Event
    {
        $latest = $this->events[count($this->events) - 1] ?? null;

        return $latest instanceof Event ? $latest : null;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return Event[]
     */
    public function all(?string $eventName = null): array
    {
        if (null === $eventName) {
            return $this->events;
        }

        $events = [];
        foreach ($this->events as $event) {
            if ($event instanceof $eventName) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
