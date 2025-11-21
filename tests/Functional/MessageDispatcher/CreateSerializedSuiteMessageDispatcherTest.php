<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobCreatedEvent;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageDispatcher\CreateSerializedSuiteMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Model\RemoteRequestType;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

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

    public function testDispatchImmediatelySuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $authenticationToken = md5((string) rand());
        $parameters = [
            md5((string) rand()) => md5((string) rand()),
            md5((string) rand()) => md5((string) rand()),
            md5((string) rand()) => md5((string) rand()),
        ];

        $event = new JobCreatedEvent($authenticationToken, $job->getId(), $job->getSuiteId(), $parameters);

        $this->dispatcher->dispatchImmediately($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateSerializedSuiteMessage(
            $authenticationToken,
            $job->getId(),
            $job->getSuiteId(),
            $parameters
        );

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchImmediatelyNotReady(): void
    {
        $jobId = (string) new Ulid();

        $assessor = ReadinessAssessorFactory::create(
            RemoteRequestType::createForSerializedSuiteCreation(),
            $jobId,
            MessageHandlingReadiness::NEVER
        );

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $dispatcher = new CreateSerializedSuiteMessageDispatcher($messageDispatcher, $assessor);

        $event = new JobCreatedEvent('api token', $jobId, 'suite id', []);

        $dispatcher->dispatchImmediately($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }
}
