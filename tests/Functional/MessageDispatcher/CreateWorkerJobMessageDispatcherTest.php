<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsReadyEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\MessageDispatcher\CreateWorkerJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\JobStore;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateWorkerJobMessageDispatcherTest extends WebTestCase
{
    private CreateWorkerJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(CreateWorkerJobMessageDispatcher::class);
        \assert($dispatcher instanceof CreateWorkerJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertArrayHasKey(MachineIsReadyEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(MessageNotHandleableEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchImmediatelyNotReady(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $event = new MachineIsReadyEvent(
            StringValue::random(),
            $job->getId(),
            '127.0.0.1',
        );

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $assessor,
            $jobStore,
        );

        $dispatcher->dispatchImmediately($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelyNoJob(): void
    {
        $jobId = Id::generate();

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $readinessAssessor,
            $jobStore,
        );

        $event = new MachineIsReadyEvent(
            StringValue::random(),
            $jobId,
            '127.0.0.1',
        );

        $dispatcher->dispatchImmediately($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $machineIpAddress = '127.0.0.1';
        $authenticationToken = StringValue::random();

        $event = new MachineIsReadyEvent($authenticationToken, $job->getId(), $machineIpAddress);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $assessor,
            $jobStore,
        );

        $dispatcher->dispatchImmediately($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateWorkerJobMessage(
            $authenticationToken,
            $job->getId(),
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testRedispatch(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineIpAddress = '127.0.0.1';
        $authenticationToken = StringValue::random();

        $message = new CreateWorkerJobMessage(
            $authenticationToken,
            $job->getId(),
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );
        $event = new MessageNotHandleableEvent($message, MessageHandlingReadiness::EVENTUALLY);

        $this->dispatcher->redispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new CreateWorkerJobMessage(
            $authenticationToken,
            $job->getId(),
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());
    }
}
