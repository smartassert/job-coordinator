<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\TerminateMachineMessage;
use App\MessageDispatcher\TerminateMachineMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

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
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    public function testDispatchMessageNotDispatched(): void
    {
        $event = new ResultsJobStateRetrievedEvent(
            md5((string) rand()),
            md5((string) rand()),
            new ResultsJobState('started', null)
        );

        $this->dispatcher->dispatch($event);

        $this->assertNoMessagesDispatched();
    }

    public function testDispatchSuccess(): void
    {
        $event = new ResultsJobStateRetrievedEvent(
            md5((string) rand()),
            md5((string) rand()),
            new ResultsJobState('complete', 'ended')
        );

        $this->dispatcher->dispatch($event);

        $this->assertDispatchedMessage($event->authenticationToken, $event->jobId);
    }

    /**
     * @return array<mixed>
     */
    public function dispatchSuccessDataProvider(): array
    {
        $jobId = md5((string) rand());

        $job = (new Job($jobId, md5((string) rand()), md5((string) rand()), 600))
            ->setResultsToken('results token')
            ->setSerializedSuiteState('prepared')
        ;

        $resultsJobCreatedEvent = new ResultsJobCreatedEvent(
            md5((string) rand()),
            $jobId,
            \Mockery::mock(ResultsJob::class)
        );

        $serializedSuiteSerializedEvent = new SerializedSuiteSerializedEvent(
            md5((string) rand()),
            $jobId,
            md5((string) rand())
        );

        return [
            'ResultsJobCreatedEvent' => [
                'job' => $job,
                'event' => $resultsJobCreatedEvent,
            ],
            'SerializedSuiteSerializedEvent' => [
                'job' => $job,
                'event' => $serializedSuiteSerializedEvent,
            ],
        ];
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $jobId): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new TerminateMachineMessage($authenticationToken, $jobId),
            $envelope->getMessage()
        );

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }
}
