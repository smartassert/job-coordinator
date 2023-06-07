<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Services\RemoteRequestRemoverForEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestRemoverForEventsTest extends WebTestCase
{
    private RemoteRequestRemoverForEvents $remoteRequestRemoverForEvents;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestRemoverForEvents = self::getContainer()->get(RemoteRequestRemoverForEvents::class);
        \assert($remoteRequestRemoverForEvents instanceof RemoteRequestRemoverForEvents);
        $this->remoteRequestRemoverForEvents = $remoteRequestRemoverForEvents;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->remoteRequestRemoverForEvents);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->remoteRequestRemoverForEvents::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
    {
        return [
            MachineIsActiveEvent::class => [
                'expectedListenedForEvent' => MachineIsActiveEvent::class,
                'expectedMethod' => 'removeMachineCreateRemoteRequestsForMachineIsActiveEvent',
            ],
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'removeResultsCreateRemoteRequestsForResultsJobCreatedEvent',
            ],
            SerializedSuiteCreatedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteCreatedEvent::class,
                'expectedMethod' => 'removeSerializedSuiteCreateRequestsForSerializedSuiteCreatedEvent',
            ],
            MachineRetrievedEvent::class => [
                'expectedListenedForEvent' => MachineRetrievedEvent::class,
                'expectedMethod' => 'removeMachineGetRemoteRequestsForMachineRetrievedEvent',
            ],
            SerializedSuiteRetrievedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteRetrievedEvent::class,
                'expectedMethod' => 'removeSerializedSuiteGetRemoteRequestsForSerializedSuiteRetrievedEvent',
            ],
        ];
    }
}
