<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine as MachineEntity;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\Model\Machine\Machine;
use App\Model\Machine\MachineInterface;
use App\Model\MetaState;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
                'expectedMethod' => 'dispatchImmediately',
            ],
            MachineRetrievedEvent::class => [
                'expectedListenedForEvent' => MachineRetrievedEvent::class,
                'expectedMethod' => 'dispatch',
            ],
        ];
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = new Machine(
            $job->getId(),
            'create/requested',
            'pre_active',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $this->persistMachine($machine);

        $event = new MachineCreationRequestedEvent($machine);

        $this->dispatcher->dispatchImmediately($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetMachineMessage($machine->getId());

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertSame([], $dispatchedEnvelope->all(DelayStamp::class));
    }

    public function testDispatchMachineIsInEndState(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = new Machine(
            $job->getId(),
            'delete/deleted',
            'end',
            null,
            null,
            new MetaState(true, false, false),
            false,
            false,
            false,
        );

        $this->persistMachine($machine);

        $event = new MachineRetrievedEvent($machine, $machine);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(0, $envelopes);
    }

    public function testDispatchSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = new Machine(
            $job->getId(),
            'create/requested',
            'pre_active',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $this->persistMachine($machine);

        $event = new MachineRetrievedEvent($machine, $machine);

        $this->dispatcher->dispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $expectedMessage = new GetMachineMessage($machine->getId());

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        self::assertEquals(
            [
                new DelayStamp($messageDelays[GetMachineMessage::class]),
            ],
            $dispatchedEnvelope->all(DelayStamp::class)
        );
    }

    private function persistMachine(MachineInterface $machine): void
    {
        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $entity = new MachineEntity(
            $machine->getId(),
            $machine->getState(),
            $machine->getStateCategory(),
        );

        $entity->setMetaState(
            new MetaState(
                $machine->getMetaState()->ended,
                $machine->getMetaState()->succeeded,
                $machine->getMetaState()->pending,
            )
        );

        $machineRepository->save($entity);
    }
}
