<?php

declare(strict_types=1);

namespace App\Tests\Services\EventSubscriber;

use App\Event\JobCreatedEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Event\SerializedSuiteSerializedEvent;
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
        ];
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getLatest(): ?Event
    {
        $latest = $this->events[0] ?? null;

        return $latest instanceof Event ? $latest : null;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
