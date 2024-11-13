<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Services\RemoteRequestRemoverForEvents;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->remoteRequestRemoverForEvents::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            MachineIsActiveEvent::class => [
                'expectedListenedForEvent' => MachineIsActiveEvent::class,
                'expectedMethod' => 'removeMachineCreateRequests',
            ],
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'removeResultsCreateRequests',
            ],
            SerializedSuiteCreatedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteCreatedEvent::class,
                'expectedMethod' => 'removeSerializedSuiteCreateRequests',
            ],
            MachineRetrievedEvent::class => [
                'expectedListenedForEvent' => MachineRetrievedEvent::class,
                'expectedMethod' => 'removeMachineGetRequests',
            ],
            SerializedSuiteRetrievedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteRetrievedEvent::class,
                'expectedMethod' => 'removeSerializedSuiteGetRequests',
            ],
            CreateWorkerJobRequestedEvent::class => [
                'expectedListenedForEvent' => CreateWorkerJobRequestedEvent::class,
                'expectedMethod' => 'removeWorkerJobCreateRequests',
            ],
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'removeResultsStateGetRequests',
            ],
            MachineTerminationRequestedEvent::class => [
                'expectedListenedForEvent' => MachineTerminationRequestedEvent::class,
                'expectedMethod' => 'removeMachineTerminationRequests',
            ],
            WorkerStateRetrievedEvent::class => [
                'expectedListenedForEvent' => WorkerStateRetrievedEvent::class,
                'expectedMethod' => 'removeWorkerStateGetRequests',
            ],
        ];
    }
}
