<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\MessageDispatcher\CreateWorkerJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\JobStore;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

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
        self::assertArrayHasKey(MachineIsActiveEvent::class, $this->dispatcher::getSubscribedEvents());
        self::assertArrayHasKey(MessageNotYetHandleableEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForMachineIsActiveEventNotReady(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $jobStore,
            $readinessAssessor,
        );

        $event = new MachineIsActiveEvent(
            md5((string) rand()),
            $job->getId(),
            '127.0.0.1',
            MachineFactory::create(
                $job->getId(),
                'state',
                'state-category',
                [
                    '127.0.0.1',
                ],
                false,
                false,
                false,
                false,
            )
        );

        $dispatcher->dispatchForMachineIsActiveEvent($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    public function testDispatchForMachineIsActiveEventNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $jobStore,
            $readinessAssessor,
        );

        $event = new MachineIsActiveEvent(
            md5((string) rand()),
            $jobId,
            '127.0.0.1',
            MachineFactory::create(
                $jobId,
                'state',
                'state-category',
                [
                    '127.0.0.1',
                ],
                false,
                false,
                false,
                false,
            )
        );

        $dispatcher->dispatchForMachineIsActiveEvent($event);

        self::assertSame([], $this->messengerTransport->getSent());
    }

    public function testDispatchForMachineIsActiveEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $jobRemoteRequestMessageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($jobRemoteRequestMessageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $readinessAssessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $readinessAssessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $dispatcher = new CreateWorkerJobMessageDispatcher(
            $jobRemoteRequestMessageDispatcher,
            $jobStore,
            $readinessAssessor,
        );

        $machineIpAddress = '127.0.0.1';
        $authenticationToken = md5((string) rand());

        $machine = MachineFactory::create(
            $job->getId(),
            'find/not-findable',
            'end',
            [],
            true,
            false,
            false,
            true,
        );

        $event = new MachineIsActiveEvent($authenticationToken, $job->getId(), $machineIpAddress, $machine);

        $dispatcher->dispatchForMachineIsActiveEvent($event);

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
        $authenticationToken = md5((string) rand());

        $message = new CreateWorkerJobMessage(
            $authenticationToken,
            $job->getId(),
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );
        $event = new MessageNotYetHandleableEvent($message);

        $this->dispatcher->reDispatch($event);

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
