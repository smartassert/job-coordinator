<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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

    #[DataProvider('eventSubscriptionsDataProvider')]
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
    public static function eventSubscriptionsDataProvider(): array
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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::create(
            $job->id,
            'create/requested',
            'pre_active',
            [],
            false,
            false,
            false,
            false,
        );

        $authenticationToken = md5((string) rand());

        $event = new MachineCreationRequestedEvent($authenticationToken, $machine);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetMachineMessage($authenticationToken, $machine->id, $machine);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }
}
