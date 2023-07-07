<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\WorkerJobStartRequestedEvent;
use App\Message\GetWorkerStateMessage;
use App\MessageDispatcher\GetWorkerStateMessageDispatcher;
use App\Repository\JobRepository;
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
        ];
    }

    public function testDispatchForWorkerJobStartRequestedEventSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $workerJob = \Mockery::mock(WorkerJob::class);
        $machineIpAddress = '127.0.0.1';

        $event = new WorkerJobStartRequestedEvent($jobId, $machineIpAddress, $workerJob);

        $this->dispatcher->dispatchForWorkerJobStartRequestedEvent($event);

        $this->assertDispatchedMessage(new GetWorkerStateMessage($jobId, $machineIpAddress));
    }

    private function assertDispatchedMessage(GetWorkerStateMessage $expected): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}
