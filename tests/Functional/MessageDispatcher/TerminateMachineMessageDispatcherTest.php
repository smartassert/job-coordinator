<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobRetrievedEvent;
use App\Message\TerminateMachineMessage;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\MessageDispatcher\TerminateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\MetaState;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class TerminateMachineMessageDispatcherTest extends WebTestCase
{
    private TerminateMachineMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(TerminateMachineMessageDispatcher::class);
        \assert($dispatcher instanceof TerminateMachineMessageDispatcher);
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
            ResultsJobRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobRetrievedEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
        ];
    }

    public function testDispatchImmediatelyNeverReady(): void
    {
        $jobId = Id::generate();

        $event = new ResultsJobRetrievedEvent(
            StringValue::random(),
            $jobId,
            new ResultsJob(
                $jobId,
                'event/add/results-token',
                new ResultsJobState(
                    'complete',
                    'ended',
                    new ResultsClientMetaState(true, true, false),
                ),
                false,
            )
        );

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new TerminateMachineMessageDispatcher($messageDispatcher, $assessor);
        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create(job: $job, endState: 'end-state');

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save(
            new Machine(
                $job->getId(),
                'state',
                'state-category',
            )->setMetaState(new MetaState(true, true, false))
        );

        $event = new ResultsJobRetrievedEvent(
            StringValue::random(),
            $job->getId(),
            new ResultsJob(
                $job->getId(),
                'event/add/results-token',
                new ResultsJobState(
                    'complete',
                    'ended',
                    new ResultsClientMetaState(true, true, false),
                ),
                false,
            ),
        );

        $this->dispatcher->dispatchImmediately($event);

        $this->assertDispatchedMessage($event->getAuthenticationToken(), $event->getJobId());
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertEquals(
            new TerminateMachineMessage($authenticationToken, $jobId),
            $envelope->getMessage()
        );

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }
}
