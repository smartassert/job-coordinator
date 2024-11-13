<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\MessageDispatcher\GetWorkerJobMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetWorkerJobMessageDispatcherTest extends WebTestCase
{
    private GetWorkerJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetWorkerJobMessageDispatcher::class);
        \assert($dispatcher instanceof GetWorkerJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->dispatcher::getSubscribedEvents();
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
            CreateWorkerJobRequestedEvent::class => [
                'expectedListenedForEvent' => CreateWorkerJobRequestedEvent::class,
                'expectedMethod' => 'dispatchForCreateWorkerJobRequestedEvent',
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
        $workerJob = WorkerClientJobFactory::createRandom();

        $machineIpAddress = '127.0.0.1';

        $event = new CreateWorkerJobRequestedEvent($jobId, $machineIpAddress, $workerJob);

        $this->dispatcher->dispatchForCreateWorkerJobRequestedEvent($event);

        $expectedMessage = new GetWorkerJobMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
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

        $expectedMessage = new GetWorkerJobMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }
}
