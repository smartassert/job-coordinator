<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use App\MessageDispatcher\StartWorkerJobMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class StartWorkerJobMessageDispatcherTest extends WebTestCase
{
    private StartWorkerJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(StartWorkerJobMessageDispatcher::class);
        \assert($dispatcher instanceof StartWorkerJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(MachineIsActiveEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForMachineIsActiveEventMessageIsNotDispatchedIfNoJob(): void
    {
        $machineId = md5((string) rand());
        $machineIpAddress = '127.0.0.1';

        $currentMachine = new Machine($machineId, 'up/active', 'active', [$machineIpAddress]);

        $event = new MachineIsActiveEvent(
            'authentication token',
            \Mockery::mock(Machine::class),
            $currentMachine,
            $machineIpAddress,
        );

        $this->dispatcher->dispatchForMachineIsActiveEvent($event);

        self::assertCount(0, $this->messengerTransport->get());
    }

    public function testDispatchForMachineIsActiveEventSuccess(): void
    {
        $jobId = md5((string) rand());
        $serializedSuiteId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 'results token', $serializedSuiteId, 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $machineIpAddress = '127.0.0.1';
        $currentMachine = new Machine($jobId, 'up/active', 'active', [$machineIpAddress]);

        $authenticationToken = md5((string) rand());

        $event = new MachineIsActiveEvent(
            $authenticationToken,
            \Mockery::mock(Machine::class),
            $currentMachine,
            $machineIpAddress
        );

        $this->dispatcher->dispatchForMachineIsActiveEvent($event);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new StartWorkerJobMessage($authenticationToken, $currentMachine->id, $machineIpAddress);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());
    }

    public function testDispatch(): void
    {
        $authenticationToken = md5((string) rand());

        $machineIpAddress = '127.0.0.1';
        $machine = new Machine(md5((string) rand()), 'up/active', 'active', [$machineIpAddress]);

        $message = new StartWorkerJobMessage($authenticationToken, $machine->id, $machineIpAddress);

        $this->dispatcher->dispatch($message);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($message, $dispatchedEnvelope->getMessage());

        $delayStamps = $dispatchedEnvelope->all(DelayStamp::class);
        self::assertCount(1, $delayStamps);

        $delayStamp = $delayStamps[0];
        self::assertInstanceOf(DelayStamp::class, $delayStamp);

        $expectedDelay = self::getContainer()->getParameter('start_worker_job_message_dispatch_delay');
        \assert(is_int($expectedDelay));

        self::assertEquals(new DelayStamp($expectedDelay), $delayStamp);
    }
}
