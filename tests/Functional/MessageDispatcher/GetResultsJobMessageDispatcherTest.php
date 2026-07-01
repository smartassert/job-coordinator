<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\ResultsJobRetrievedEvent;
use App\Message\GetResultsJobMessage;
use App\MessageDispatcher\GetResultsJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Model\JobInterface;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\StringValue;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
use SmartAssert\WorkerClient\Model\Job as WorkerJob;
use SmartAssert\WorkerClient\Model\ResourceReference;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetResultsJobMessageDispatcherTest extends WebTestCase
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
        $dispatcher = self::getContainer()->get(GetResultsJobMessageDispatcher::class);
        \assert($dispatcher instanceof GetResultsJobMessageDispatcher);

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
            CreateWorkerJobRequestedEvent::class => [
                'expectedListenedForEvent' => CreateWorkerJobRequestedEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
            ResultsJobRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): CreateWorkerJobRequestedEvent $eventCreator
     */
    #[DataProvider('dispatchImmediatelyEventDataProvider')]
    public function testDispatchImmediatelyNotReady(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = StringValue::random();

        $event = $eventCreator($job, $authenticationToken);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new GetResultsJobMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): CreateWorkerJobRequestedEvent $eventCreator
     */
    #[DataProvider('dispatchImmediatelyEventDataProvider')]
    public function testDispatchImmediatelySuccess(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = StringValue::random();

        $event = $eventCreator($job, $authenticationToken);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $dispatcher = new GetResultsJobMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatchImmediately($event);

        $this->assertDispatchedMessage(new GetResultsJobMessage($authenticationToken, $job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchImmediatelyEventDataProvider(): array
    {
        return [
            CreateWorkerJobRequestedEvent::class => [
                'eventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new CreateWorkerJobRequestedEvent(
                        $authenticationToken,
                        $job->getId(),
                        '127.0.0.1',
                        new WorkerJob(
                            new ResourceReference(
                                $job->getId(),
                                StringValue::random(),
                            ),
                            600,
                            [],
                            [],
                            [],
                            [],
                            [],
                        ),
                    );
                },
            ],
        ];
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): ResultsJobRetrievedEvent $eventCreator
     */
    #[DataProvider('dispatchEventDataProvider')]
    public function testDispatchNotReady(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = StringValue::random();

        $event = $eventCreator($job, $authenticationToken);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new GetResultsJobMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatch($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    /**
     * @param callable(JobInterface $job, string $authenticationToken): ResultsJobRetrievedEvent $eventCreator
     */
    #[DataProvider('dispatchEventDataProvider')]
    public function testDispatchSuccess(callable $eventCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = StringValue::random();

        $event = $eventCreator($job, $authenticationToken);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $dispatcher = new GetResultsJobMessageDispatcher($messageDispatcher, $assessor);

        $dispatcher->dispatch($event);

        $this->assertDispatchedMessage(new GetResultsJobMessage($authenticationToken, $job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchEventDataProvider(): array
    {
        return [
            ResultsJobRetrievedEvent::class => [
                'eventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new ResultsJobRetrievedEvent(
                        $authenticationToken,
                        $job->getId(),
                        new ResultsJob(
                            $job->getId(),
                            '/event/add/results-token',
                            new ResultsJobState(
                                'awaiting-events',
                                null,
                                new ResultsClientMetaState(false, false, true),
                            ),
                            false,
                        ),
                    );
                },
            ],
        ];
    }

    private function assertDispatchedMessage(GetResultsJobMessage $expected): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}
