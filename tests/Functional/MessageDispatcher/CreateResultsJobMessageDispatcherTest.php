<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobCreatedEvent;
use App\Message\CreateResultsJobMessage;
use App\MessageDispatcher\CreateResultsJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use App\Tests\Services\Factory\JobFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        self::assertArrayHasKey(JobCreatedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());

        $event = new JobCreatedEvent($authenticationToken, $job->getId(), []);

        $this->dispatcher->dispatchForJobCreatedEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateResultsJobMessage($authenticationToken, $job->getId());

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchNotReady(): void
    {
        $readinessAssessor = \Mockery::mock(CreateResultsJobReadinessAssessor::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $dispatcher = new CreateResultsJobMessageDispatcher($messageDispatcher, $readinessAssessor);

        $event = new JobCreatedEvent('api token', 'job id', []);

        $dispatcher->dispatchForJobCreatedEvent($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }
}
