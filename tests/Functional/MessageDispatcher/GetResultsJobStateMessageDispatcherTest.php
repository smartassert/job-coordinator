<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use App\MessageDispatcher\GetResultsJobStateMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'dispatchForResultsJobEvent',
            ],
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'dispatchForResultsJobEvent',
            ],
        ];
    }

    public function testDispatchForResultsJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $authenticationToken = md5((string) rand());
        $resultsToken = md5((string) rand());
        $resultsJob = new ResultsJob($job->getId(), $resultsToken, new JobState('awaiting-events', null));

        $event = new ResultsJobCreatedEvent($authenticationToken, $job->getId(), $resultsJob);

        $this->dispatcher->dispatchForResultsJobEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $job->getId()));
    }

    public function testDispatchForResultsJobStateRetrievedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $authenticationToken = md5((string) rand());
        $resultsJobState = new ResultsJobState('started', null);

        $event = new ResultsJobStateRetrievedEvent($authenticationToken, $job->getId(), $resultsJobState);

        $this->dispatcher->dispatchForResultsJobEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $job->getId()));
    }

    private function assertDispatchedMessage(GetResultsJobStateMessage $expected): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}
