<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Job;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class GetMachineMessageDispatcherTest extends WebTestCase
{
    private GetMachineMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetMachineMessageDispatcher::class);
        \assert($dispatcher instanceof GetMachineMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->dispatcher::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
    {
        return [
            MachineCreationRequestedEvent::class => [
                'expectedListenedForEvent' => MachineCreationRequestedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
            MachineRetrievedEvent::class => [
                'expectedListenedForEvent' => MachineRetrievedEvent::class,
                'expectedMethod' => 'dispatchIfMachineNotInEndState',
            ],
        ];
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

        $event = new MachineCreationRequestedEvent($authenticationToken, $machine);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetMachineMessage($authenticationToken, $machine->id, $machine);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }
}
