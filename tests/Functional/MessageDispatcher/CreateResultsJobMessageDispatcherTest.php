<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Message\CreateResultsJobMessage;
use App\MessageDispatcher\CreateResultsJobMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateResultsJobMessageDispatcherTest extends WebTestCase
{
    private CreateResultsJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateResultsJobMessageDispatcher::class);
        \assert($dispatcher instanceof CreateResultsJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(JobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());

        $event = new JobCreatedEvent($authenticationToken, $job->id, []);

        $this->dispatcher->dispatchForJobCreatedEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateResultsJobMessage($authenticationToken, $job->id);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }
}
