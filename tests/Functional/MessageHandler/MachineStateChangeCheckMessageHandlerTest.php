<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use App\MessageHandler\MachineStateChangeCheckMessageHandler;
use App\Tests\Services\AuthenticationConfiguration;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class MachineStateChangeCheckMessageHandlerTest extends WebTestCase
{
    private AuthenticationConfiguration $authenticationConfiguration;
    private EventRecorder $eventRecorder;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $authenticationConfiguration = self::getContainer()->get(AuthenticationConfiguration::class);
        \assert($authenticationConfiguration instanceof AuthenticationConfiguration);
        $this->authenticationConfiguration = $authenticationConfiguration;

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(MachineStateChangeCheckMessageHandler::class);
        self::assertInstanceOf(MachineStateChangeCheckMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    /**
     * @dataProvider invokeNoStateChangeDataProvider
     */
    public function testInvokeNoStateChange(Machine $previous, Machine $current): void
    {
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();

        $this->createMessageAndHandleMessage($previous, $current, $authenticationToken);

        self::assertNull($this->eventRecorder->getLatest());
        $this->assertDispatchedMessage($authenticationToken, $current);
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
     * @param class-string $expectedEventClass
     */
    public function testInvokeHasStateChange(Machine $previous, Machine $current, string $expectedEventClass): void
    {
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();

        $this->createMessageAndHandleMessage($previous, $current, $authenticationToken);

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->current);
        self::assertEquals($authenticationToken, $latestEvent->authenticationToken);

        $this->assertDispatchedMessage($authenticationToken, $current);
    }

    /**
     * @return array<mixed>
     */
    public function invokeHasStateChangeDataProvider(): array
    {
        $machineId = md5((string) rand());

        return [
            'unknown => find/received' => [
                'previous' => new Machine($machineId, 'unknown', 'unknown', []),
                'current' => new Machine($machineId, 'find/received', 'finding', []),
                'expectedEventClass' => MachineStateChangeEvent::class,
            ],
            'unknown => active' => [
                'previous' => new Machine($machineId, 'unknown', 'unknown', []),
                'current' => new Machine($machineId, 'up/active', 'active', []),
                'expectedEventClass' => MachineIsActiveEvent::class,
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
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();

        $this->createMessageAndHandleMessage($previous, $current, $authenticationToken);

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->current);
        self::assertEquals($authenticationToken, $latestEvent->authenticationToken);

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

    /**
     * @param non-empty-string $authenticationToken
     */
    private function createMessageAndHandleMessage(
        Machine $previous,
        Machine $current,
        string $authenticationToken,
    ): void {
        $messageDispatcher = self::getContainer()->get(MachineStateChangeCheckMessageDispatcher::class);
        \assert($messageDispatcher instanceof MachineStateChangeCheckMessageDispatcher);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->with($authenticationToken, $previous->id)
            ->andReturn($current)
        ;

        $handler = new MachineStateChangeCheckMessageHandler(
            $messageDispatcher,
            $workerManagerClient,
            $eventDispatcher
        );

        $message = new MachineStateChangeCheckMessage($authenticationToken, $previous);

        ($handler)($message);
    }

    /**
     * @param non-empty-string $authenticationToken
     */
    private function assertDispatchedMessage(string $authenticationToken, Machine $current): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new MachineStateChangeCheckMessage($authenticationToken, $current),
            $envelope->getMessage()
        );
    }
}
