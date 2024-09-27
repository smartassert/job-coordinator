<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use App\MessageDispatcher\GetResultsJobStateMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetResultsJobStateMessageDispatcherTest extends WebTestCase
{
    private GetResultsJobStateMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetResultsJobStateMessageDispatcher::class);
        \assert($dispatcher instanceof GetResultsJobStateMessageDispatcher);
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
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'dispatchForResultsJobCreatedEvent',
            ],
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'dispatchForResultsJobStateRetrievedEvent',
            ],
        ];
    }

    public function testDispatchForResultsJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());
        $resultsToken = md5((string) rand());
        $resultsJob = new ResultsJob($job->id, $resultsToken, new JobState('awaiting-events', null));

        $event = new ResultsJobCreatedEvent($authenticationToken, $job->id, $resultsJob);

        $this->dispatcher->dispatchForResultsJobCreatedEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $job->id));
    }

    public function testDispatchForResultsJobStateRetrievedEventNotEndState(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());
        $resultsJobState = new ResultsJobState('started', null);

        $event = new ResultsJobStateRetrievedEvent($authenticationToken, $job->id, $resultsJobState);

        $this->dispatcher->dispatchForResultsJobStateRetrievedEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $job->id));
    }

    public function testDispatchForResultsJobStateRetrievedEventIsEndState(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());
        $resultsJobState = new ResultsJobState('complete', 'ended');

        $event = new ResultsJobStateRetrievedEvent($authenticationToken, $job->id, $resultsJobState);

        $this->dispatcher->dispatchForResultsJobStateRetrievedEvent($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    private function assertDispatchedMessage(GetResultsJobStateMessage $expected): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}
