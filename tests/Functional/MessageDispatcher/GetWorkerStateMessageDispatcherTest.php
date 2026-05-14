<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Message\GetWorkerStateMessage;
use App\MessageDispatcher\GetWorkerStateMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Messenger\NonDelayedStamp;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\Machine;
use SmartAssert\WorkerManagerClient\Model\MetaState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class GetWorkerStateMessageDispatcherTest extends WebTestCase
{
    private GetWorkerStateMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetWorkerStateMessageDispatcher::class);
        \assert($dispatcher instanceof GetWorkerStateMessageDispatcher);
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
        ];
    }

    public function testDispatchImmediatelyNotReady(): void
    {
        $jobId = md5((string) rand());

        $machineIpAddress = '127.0.0.1';
        $machine = new Machine(
            $jobId,
            'up/active',
            'active',
            [$machineIpAddress],
            null,
            false,
            true,
            false,
            false,
            new MetaState(false, false),
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

        $dispatcher = new GetWorkerStateMessageDispatcher($messageDispatcher, $assessor);
        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobId = md5((string) rand());

        $machineIpAddress = '127.0.0.1';
        $machine = new Machine(
            $jobId,
            'up/active',
            'active',
            [$machineIpAddress],
            null,
            false,
            true,
            false,
            false,
            new MetaState(false, false),
        );

        $event = new MachineIsActiveEvent('authentication-token', $jobId, $machineIpAddress, $machine);

        $this->dispatcher->dispatchImmediately($event);

        $expectedMessage = new GetWorkerStateMessage($jobId, $machineIpAddress);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }
}
