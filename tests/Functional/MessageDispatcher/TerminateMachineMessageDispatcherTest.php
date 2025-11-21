<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\TerminateMachineMessage;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\MessageDispatcher\TerminateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

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
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
        ];
    }

    public function testDispatchImmediatelyNeverReady(): void
    {
        $jobId = md5((string) rand());

        $event = new ResultsJobStateRetrievedEvent(
            md5((string) rand()),
            $jobId,
            new ResultsJobState('complete', 'ended')
        );

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForMachineTermination(),
            $jobId,
            MessageHandlingReadiness::NEVER
        );

        $dispatcher = new TerminateMachineMessageDispatcher($messageDispatcher, $assessor);
        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testRedispatchNeverReady(): void
    {
        $jobId = (string) new Ulid();

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForMachineTermination(),
            $jobId,
            MessageHandlingReadiness::NEVER
        );

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $dispatcher = new TerminateMachineMessageDispatcher($messageDispatcher, $assessor);

        $message = new TerminateMachineMessage('api token', $jobId);
        $event = new MessageNotHandleableEvent($message, MessageHandlingReadiness::EVENTUALLY);

        $dispatcher->redispatch($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJob = $resultsJobFactory->create($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save(
            new Machine(
                $job->getId(),
                'state',
                'state-category',
                false,
                true,
            )
        );

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsJob->setEndState('end-state');
        $resultsJobRepository->save($resultsJob);

        $event = new ResultsJobStateRetrievedEvent(
            md5((string) rand()),
            $job->getId(),
            new ResultsJobState('complete', 'ended')
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
