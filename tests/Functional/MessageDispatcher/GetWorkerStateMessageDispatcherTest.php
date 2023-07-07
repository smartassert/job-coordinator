<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\WorkerJobStartRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerStateMessage;
use App\MessageDispatcher\GetWorkerStateMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use SmartAssert\WorkerClient\Model\Job as WorkerJob;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetWorkerStateMessageDispatcherTest extends WebTestCase
{
    private GetWorkerStateMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetWorkerStateMessageDispatcher::class);
        \assert($dispatcher instanceof GetWorkerStateMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->dispatcher::getSubscribedEvents();
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
            WorkerJobStartRequestedEvent::class => [
                'expectedListenedForEvent' => WorkerJobStartRequestedEvent::class,
                'expectedMethod' => 'dispatchForWorkerJobStartRequestedEvent',
            ],
            WorkerStateRetrievedEvent::class => [
                'expectedListenedForEvent' => WorkerStateRetrievedEvent::class,
                'expectedMethod' => 'dispatchForWorkerStateRetrievedEvent',
            ],
        ];
    }

    public function testDispatchForWorkerJobStartRequestedEventSuccess(): void
    {
        $jobId = md5((string) rand());
        $workerJob = \Mockery::mock(WorkerJob::class);
        $machineIpAddress = '127.0.0.1';

        $event = new WorkerJobStartRequestedEvent($jobId, $machineIpAddress, $workerJob);

        $this->dispatcher->dispatchForWorkerJobStartRequestedEvent($event);

        $expectedMessage = new GetWorkerStateMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }

    public function testDispatchForWorkerStateRetrievedEventIsEndState(): void
    {
        $jobId = md5((string) rand());

        $state = new ApplicationState(
            new ComponentState('complete', true),
            new ComponentState('complete', true),
            new ComponentState('complete', true),
            new ComponentState('complete', true),
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $event = new WorkerStateRetrievedEvent($jobId, $machineIpAddress, $state);

        $this->dispatcher->dispatchForWorkerStateRetrievedEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    public function testDispatchForWorkerStateRetrievedEventMessageIsDispatched(): void
    {
        $jobId = md5((string) rand());

        $state = new ApplicationState(
            new ComponentState('executing', false),
            new ComponentState('complete', true),
            new ComponentState('running', false),
            new ComponentState('running', false),
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $event = new WorkerStateRetrievedEvent($jobId, $machineIpAddress, $state);

        $this->dispatcher->dispatchForWorkerStateRetrievedEvent($event);

        $expectedMessage = new GetWorkerStateMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }
}
