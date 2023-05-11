<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\MachineRequestedEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class MachineStateChangeCheckMessageDispatcherTest extends WebTestCase
{
    private MachineStateChangeCheckMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(MachineStateChangeCheckMessageDispatcher::class);
        \assert($dispatcher instanceof MachineStateChangeCheckMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->dispatcher);
        self::assertArrayHasKey(MachineRequestedEvent::class, $this->dispatcher::getSubscribedEvents());
    }

    public function testDispatchSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = new Job($jobId, 'user id', 'suite id', 600);
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        $machine = new Machine($jobId, 'create/requested', 'pre_active', []);

        $authenticationToken = md5((string) rand());

        $event = new MachineRequestedEvent($authenticationToken, $machine);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new MachineStateChangeCheckMessage($authenticationToken, $machine);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }
}
