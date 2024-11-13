<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageDispatcher\CreateSerializedSuiteMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\SerializedSuiteFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateSerializedSuiteMessageDispatcherTest extends WebTestCase
{
    private CreateSerializedSuiteMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateSerializedSuiteMessageDispatcher::class);
        \assert($dispatcher instanceof CreateSerializedSuiteMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertArrayHasKey(JobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $authenticationToken = md5((string) rand());
        $parameters = [
            md5((string) rand()) => md5((string) rand()),
            md5((string) rand()) => md5((string) rand()),
            md5((string) rand()) => md5((string) rand()),
        ];

        $event = new JobCreatedEvent($authenticationToken, $job->getId(), $parameters);

        $this->dispatcher->dispatchForJobCreatedEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateSerializedSuiteMessage($authenticationToken, $job->getId(), $parameters);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchResultsJobAlreadyExists(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuiteFactory->createPreparedForJob($job);

        $event = new JobCreatedEvent('api token', $job->getId(), []);

        $this->dispatcher->dispatchForJobCreatedEvent($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }
}
