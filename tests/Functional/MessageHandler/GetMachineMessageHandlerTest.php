<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\Event;

class GetMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private EventRecorder $eventRecorder;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(GetMachineMessageHandler::class);
        self::assertInstanceOf(GetMachineMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testEventsAreDispatched(): void
    {
        $machineId = md5((string) rand());

        $previous = new Machine($machineId, 'find/received', 'finding', []);
        $current = new Machine($machineId, 'find/received', 'finding', []);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        self::assertEquals(
            new MachineRetrievedEvent(self::$apiToken, $previous, $current),
            $this->eventRecorder->getLatest()
        );
    }

    /**
     * @dataProvider invokeNoStateChangeDataProvider
     */
    public function testInvokeNoStateChange(Machine $previous, Machine $current): void
    {
        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        self::assertEquals(
            new MachineRetrievedEvent(self::$apiToken, $previous, $current),
            $this->eventRecorder->getLatest()
        );
        $this->assertDispatchedMessage(self::$apiToken, $current);
    }

    /**
     * @return array<mixed>
     */
    public function invokeNoStateChangeDataProvider(): array
    {
        $machineId = md5((string) rand());

        return [
            'find/received => find/received' => [
                'previous' => new Machine($machineId, 'find/received', 'finding', []),
                'current' => new Machine($machineId, 'find/received', 'finding', []),
                'expectedEvent' => null,
                'machineCreator' => function (string $machineId) {
                    \assert('' !== $machineId);

                    return new Machine($machineId, 'find/received', 'finding', []);
                },
                'expectedMachineCreator' => function (string $machineId) {
                    \assert('' !== $machineId);

                    return new Machine($machineId, 'find/received', 'finding', []);
                },
                'expectedEventCreator' => function () {
                    return null;
                },
            ],
        ];
    }

    /**
     * @dataProvider invokeHasStateChangeDataProvider
     *
     * @param callable(string): Event $expectedEventCreator
     */
    public function testInvokeHasStateChange(Machine $previous, Machine $current, callable $expectedEventCreator): void
    {
        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        $expectedEvent = $expectedEventCreator(self::$apiToken);

        $events = $this->eventRecorder->all($expectedEvent::class);
        $event = $events[0] ?? null;

        self::assertNotNull($event);
        self::assertEquals($expectedEvent, $event);

        $this->assertDispatchedMessage(self::$apiToken, $current);
    }

    /**
     * @return array<mixed>
     */
    public function invokeHasStateChangeDataProvider(): array
    {
        $machineId = md5((string) rand());
        $machineUnknown = new Machine($machineId, 'unknown', 'unknown', []);
        $machineFinding = new Machine($machineId, 'find/received', 'finding', []);
        $machineIpAddress = '127.0.0.1';
        $machineActive = new Machine($machineId, 'up/active', 'active', [$machineIpAddress]);

        return [
            'unknown => find/received' => [
                'previous' => $machineUnknown,
                'current' => new Machine($machineId, 'find/received', 'finding', []),
                'expectedEventCreator' => function (
                    string $authenticationToken
                ) use (
                    $machineUnknown,
                    $machineFinding
                ) {
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent($authenticationToken, $machineUnknown, $machineFinding);
                },
            ],
            'unknown => active' => [
                'previous' => $machineUnknown,
                'current' => $machineActive,
                'expectedEventCreator' => function (string $authenticationToken) use ($machineId, $machineIpAddress) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent($authenticationToken, $machineId, $machineIpAddress);
                },
            ],
        ];
    }

    /**
     * @dataProvider invokeHasEndStateChangeDataProvider
     *
     * @param class-string $expectedEventClass
     */
    public function testInvokeHasEndStateChange(Machine $previous, Machine $current, string $expectedEventClass): void
    {
        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->current);
        self::assertEquals(self::$apiToken, $latestEvent->authenticationToken);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @return array<mixed>
     */
    public function invokeHasEndStateChangeDataProvider(): array
    {
        $machineId = md5((string) rand());

        return [
            'up/active => delete/deleted' => [
                'previous' => new Machine($machineId, 'up/active', 'active', []),
                'current' => new Machine($machineId, 'delete/deleted', 'end', []),
                'expectedEventClass' => MachineStateChangeEvent::class,
            ],
        ];
    }

    protected function getHandlerClass(): string
    {
        return GetMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetMachineMessage::class;
    }

    /**
     * @param non-empty-string $authenticationToken
     */
    private function createMessageAndHandleMessage(
        Machine $previous,
        Machine $current,
        string $authenticationToken,
    ): void {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->with($authenticationToken, $previous->id)
            ->andReturn($current)
        ;

        $handler = new GetMachineMessageHandler($workerManagerClient, $eventDispatcher);
        $message = new GetMachineMessage($authenticationToken, $previous);

        ($handler)($message);
    }

    /**
     * @param non-empty-string $authenticationToken
     */
    private function assertDispatchedMessage(string $authenticationToken, Machine $current): void
    {
        $envelopes = $this->messengerTransport->get();

        $machineStateChangeCheckMessage = null;
        $machineStateChangeCheckMessageDelayStamps = [];
        $foundMessageClasses = [];

        foreach ($envelopes as $envelope) {
            if ($envelope instanceof Envelope) {
                $message = $envelope->getMessage();
                $foundMessageClasses[] = $message::class;

                if (GetMachineMessage::class === $message::class) {
                    $machineStateChangeCheckMessage = $message;
                    $machineStateChangeCheckMessageDelayStamps = $envelope->all(DelayStamp::class);
                }
            }
        }

        if (null === $machineStateChangeCheckMessage) {
            self::fail(sprintf(
                '%s message not dispatched, found: %s',
                GetMachineMessage::class,
                implode(', ', $foundMessageClasses)
            ));
        }

        self::assertEquals(
            new GetMachineMessage($authenticationToken, $current),
            $machineStateChangeCheckMessage
        );

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[GetMachineMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $machineStateChangeCheckMessageDelayStamps);
    }
}
