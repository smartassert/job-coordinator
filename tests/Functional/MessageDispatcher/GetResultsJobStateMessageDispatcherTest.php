<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use App\MessageDispatcher\GetResultsJobStateMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

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
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(ResultsJobStateRetrievedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForResultsJobCreatedEventSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $authenticationToken = md5((string) rand());
        $resultsToken = md5((string) rand());
        $resultsJob = new ResultsJob($jobId, $resultsToken);

        $event = new ResultsJobCreatedEvent($authenticationToken, $jobId, $resultsJob);

        $this->dispatcher->dispatchForResultsJobCreatedEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $jobId));
    }

    public function testDispatchForResultsJobStateRetrievedEventNotEndState(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $authenticationToken = md5((string) rand());
        $resultsJobState = new ResultsJobState('started', null);

        $event = new ResultsJobStateRetrievedEvent($authenticationToken, $jobId, $resultsJobState);

        $this->dispatcher->dispatchForResultsJobStateRetrievedEvent($event);

        $this->assertDispatchedMessage(new GetResultsJobStateMessage($authenticationToken, $jobId));
    }

    public function testDispatchForResultsJobStateRetrievedEventIsEndState(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $authenticationToken = md5((string) rand());
        $resultsJobState = new ResultsJobState('complete', 'ended');

        $event = new ResultsJobStateRetrievedEvent($authenticationToken, $jobId, $resultsJobState);

        $this->dispatcher->dispatchForResultsJobStateRetrievedEvent($event);

        self::assertSame([], $this->messengerTransport->get());
    }

    private function assertDispatchedMessage(GetResultsJobStateMessage $expected): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expected, $dispatchedEnvelope->getMessage());
    }
}