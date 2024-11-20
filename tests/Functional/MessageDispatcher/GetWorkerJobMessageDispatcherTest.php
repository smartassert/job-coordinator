<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\MessageDispatcher\GetWorkerJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
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
                'expectedMethod' => 'dispatch',
            ],
            WorkerStateRetrievedEvent::class => [
                'expectedListenedForEvent' => WorkerStateRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    public function testDispatchNotReady(): void
    {
        $jobId = md5((string) rand());
        $workerJob = WorkerClientJobFactory::createRandom();

        $machineIpAddress = '127.0.0.1';

        $event = new CreateWorkerJobRequestedEvent($jobId, $machineIpAddress, $workerJob);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new GetWorkerJobMessageDispatcher($messageDispatcher, $assessor);
        $dispatcher->dispatch($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchSuccess(): void
    {
        $jobId = md5((string) rand());
        $workerJob = WorkerClientJobFactory::createRandom();

        $machineIpAddress = '127.0.0.1';

        $event = new CreateWorkerJobRequestedEvent($jobId, $machineIpAddress, $workerJob);

        $this->dispatcher->dispatch($event);

        $expectedMessage = new GetWorkerJobMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }
}
