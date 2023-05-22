<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\SerializedSuiteCreatedEvent;
use App\Message\GetSerializedSuiteMessage;
use App\MessageDispatcher\GetSerializedSuiteStateMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class GetSerializedSuiteStateMessageDispatcherTest extends WebTestCase
{
    private GetSerializedSuiteStateMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetSerializedSuiteStateMessageDispatcher::class);
        \assert($dispatcher instanceof GetSerializedSuiteStateMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(SerializedSuiteCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForSerializedSuiteCreatedEventSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $authenticationToken = md5((string) rand());

        $serializedSuiteId = md5((string) rand());
        $serializedSuite = \Mockery::mock(SerializedSuite::class);
        $serializedSuite
            ->shouldReceive('getId')
            ->andReturn($serializedSuiteId)
        ;

        $event = new SerializedSuiteCreatedEvent($authenticationToken, $jobId, $serializedSuite);

        $this->dispatcher->dispatchForSerializedSuiteCreatedEvent($event);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetSerializedSuiteMessage($authenticationToken, $serializedSuiteId);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }
}
