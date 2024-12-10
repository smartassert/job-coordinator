<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Message\GetSerializedSuiteMessage;
use App\MessageDispatcher\GetSerializedSuiteMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\SerializedSuiteFactory;
use App\Tests\Services\Factory\SourcesClientSerializedSuiteFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetSerializedSuiteMessageDispatcherTest extends WebTestCase
{
    private GetSerializedSuiteMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetSerializedSuiteMessageDispatcher::class);
        \assert($dispatcher instanceof GetSerializedSuiteMessageDispatcher);
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
            SerializedSuiteCreatedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteCreatedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
            SerializedSuiteRetrievedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    public function testDispatchSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuite = $serializedSuiteFactory->createNewForJob($job);
        \assert('' !== $serializedSuite->id);

        $authenticationToken = md5((string) rand());
        $serializedSuiteModel = SourcesClientSerializedSuiteFactory::create($serializedSuite->id, $job->getSuiteId());

        $event = new SerializedSuiteCreatedEvent($authenticationToken, $job->getId(), $serializedSuiteModel);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetSerializedSuiteMessage(
            $authenticationToken,
            $job->getId(),
            $job->getSuiteId(),
            $serializedSuite->id
        );

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchNotReady(): void
    {
        $jobId = md5((string) rand());
        $authenticationToken = md5((string) rand());
        $suiteId = md5((string) rand());
        $serializedSuiteId = md5((string) rand());
        $serializedSuite = SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $suiteId);

        $dispatcher = self::getContainer()->get(GetSerializedSuiteMessageDispatcher::class);
        \assert($dispatcher instanceof GetSerializedSuiteMessageDispatcher);

        $event = new SerializedSuiteCreatedEvent($authenticationToken, $jobId, $serializedSuite);

        $dispatcher->dispatch($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }
}
