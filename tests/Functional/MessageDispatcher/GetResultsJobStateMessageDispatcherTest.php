<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobEventInterface;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use App\MessageDispatcher\GetResultsJobStateMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetResultsJobStateMessageDispatcherTest extends WebTestCase
{
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $dispatcher = self::getContainer()->get(GetResultsJobStateMessageDispatcher::class);
        \assert($dispatcher instanceof GetResultsJobStateMessageDispatcher);

        $subscribedEvents = $dispatcher::getSubscribedEvents();
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
                'expectedMethod' => 'dispatch',
            ],
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): JobEventInterface $eventCreator
     */
    #[DataProvider('eventDataProvider')]
    public function testDispatchNotReady(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());

        $event = $eventCreator($job, $authenticationToken);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof ResultsJobStateRetrievedEvent);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForResultsJobRetrieval(),
            $job->getId(),
            MessageHandlingReadiness::NEVER
        );

        $dispatcher = new GetResultsJobStateMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatch($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): JobEventInterface $eventCreator
     */
    #[DataProvider('eventDataProvider')]
    public function testDispatchSuccess(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());

        $event = $eventCreator($job, $authenticationToken);
        \assert($event instanceof ResultsJobCreatedEvent || $event instanceof ResultsJobStateRetrievedEvent);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForResultsJobRetrieval(),
            $job->getId(),
            MessageHandlingReadiness::NOW
        );

        $dispatcher = new GetResultsJobStateMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatch($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function eventDataProvider(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                'eventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new ResultsJobCreatedEvent(
                        $authenticationToken,
                        $job->getId(),
                        new ResultsJob($job->getId(), 'token', new ResultsJobState('awaiting-events', null))
                    );
                },
            ],
            ResultsJobStateRetrievedEvent::class => [
                'eventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new ResultsJobStateRetrievedEvent(
                        $authenticationToken,
                        $job->getId(),
                        new ResultsJobState('awaiting-events', null)
                    );
                },
            ],
        ];
    }

    private function assertDispatchedMessage(GetResultsJobStateMessage $expected): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}
