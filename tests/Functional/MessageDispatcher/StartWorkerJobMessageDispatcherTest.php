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

    public function testDispatchMessageIsNotDispatchedIfNoJob(): void
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

        $this->dispatcher->dispatch($event);

        self::assertCount(0, $this->messengerTransport->get());
    }

    public function testDispatchSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 'results token', 'serialized suite id');
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

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new StartWorkerJobMessage($authenticationToken, $currentMachine);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());
    }
}
