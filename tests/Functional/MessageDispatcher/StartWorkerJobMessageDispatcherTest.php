<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Event\NotReadyToStartWorkerJobEvent;
use App\Message\StartWorkerJobMessage;
use App\MessageDispatcher\StartWorkerJobMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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
        self::assertArrayHasKey(NotReadyToStartWorkerJobEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchForMachineIsActiveEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineIpAddress = '127.0.0.1';
        $authenticationToken = md5((string) rand());

        $event = new MachineIsActiveEvent($authenticationToken, $job->id, $machineIpAddress);

        $this->dispatcher->dispatchForMachineIsActiveEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new StartWorkerJobMessage($authenticationToken, $job->id, $machineIpAddress);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchForNotReadyToStartWorkerJobEvent(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineIpAddress = '127.0.0.1';
        $authenticationToken = md5((string) rand());

        $message = new StartWorkerJobMessage($authenticationToken, $job->id, $machineIpAddress);
        $event = new NotReadyToStartWorkerJobEvent($message);

        $this->dispatcher->dispatchForNotReadyToStartWorkerJobEvent($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new StartWorkerJobMessage($authenticationToken, $job->id, $machineIpAddress);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());
    }
}
