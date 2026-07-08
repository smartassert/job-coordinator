<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\IsWorkerReadyMessage;
use App\MessageDispatcher\IsWorkerReadyMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\Machine;
use SmartAssert\WorkerManagerClient\Model\MetaState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class IsWorkerReadyMessageDispatcherTest extends WebTestCase
{
    private IsWorkerReadyMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(IsWorkerReadyMessageDispatcher::class);
        \assert($dispatcher instanceof IsWorkerReadyMessageDispatcher);
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
            MachineIsActiveEvent::class => [
                'expectedListenedForEvent' => MachineIsActiveEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
            MessageNotHandleableEvent::class => [
                'expectedListenedForEvent' => MessageNotHandleableEvent::class,
                'expectedMethod' => 'redispatch',
            ],
        ];
    }

    public function testDispatchImmediatelyNotReady(): void
    {
        $jobId = Id::generate();

        $machineIpAddress = '127.0.0.1';
        $machine = new Machine(
            $jobId,
            'up/active',
            'active',
            [$machineIpAddress],
            null,
            false,
            false,
            new MetaState(false, false, false),
        );

        $event = new MachineIsActiveEvent('authentication-token', $jobId, $machineIpAddress, $machine);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $dispatcher = new IsWorkerReadyMessageDispatcher($messageDispatcher, $assessor);
        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobId = Id::generate();
        $authenticationToken = StringValue::random();

        $machineIpAddress = '127.0.0.1';
        $machine = new Machine(
            $jobId,
            'up/active',
            'active',
            [$machineIpAddress],
            null,
            true,
            false,
            new MetaState(false, false, false),
        );

        $event = new MachineIsActiveEvent($authenticationToken, $jobId, $machineIpAddress, $machine);

        $this->dispatcher->dispatchImmediately($event);

        $expectedMessage = new IsWorkerReadyMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }

    public function testRedispatch(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineIpAddress = '127.0.0.1';

        $message = new IsWorkerReadyMessage($job->getId(), $machineIpAddress);
        $event = new MessageNotHandleableEvent($message, MessageHandlingReadiness::EVENTUALLY);

        $this->dispatcher->redispatch($event);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($message, $dispatchedEnvelope->getMessage());
    }
}
