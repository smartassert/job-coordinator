<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageHandler\MachineStateChangeCheckMessageHandler;
use App\Tests\Services\AuthenticationConfiguration;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeCheckMessageHandlerTest extends WebTestCase
{
    private MachineStateChangeCheckMessageHandler $handler;
    private AuthenticationConfiguration $authenticationConfiguration;
    private EventRecorder $eventRecorder;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(MachineStateChangeCheckMessageHandler::class);
        \assert($handler instanceof MachineStateChangeCheckMessageHandler);
        $this->handler = $handler;

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

    /**
     * @dataProvider invokeDataProvider
     *
     * @param non-empty-string $machineId
     */
    public function testInvoke(
        string $machineId,
        string $currentMachineState,
        string $expectedNewMachineState,
        ?Event $expectedEvent,
    ): void {
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();
        $message = new MachineStateChangeCheckMessage($authenticationToken, $machineId, $currentMachineState);

        ($this->handler)($message);

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertEquals($expectedEvent, $latestEvent);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals($message->withCurrentState($expectedNewMachineState), $envelope->getMessage());
    }

    /**
     * @return array<mixed>
     */
    public function invokeDataProvider(): array
    {
        $machineId = md5((string) rand());
        $unknownMachineState = 'unknown';
        $findReceivedMachineState = 'find/received';

        return [
            'no state change' => [
                'machineId' => $machineId,
                'currentMachineState' => $findReceivedMachineState,
                'expectedNewMachineState' => $findReceivedMachineState,
                'expectedEvent' => null,
            ],
            'has state change' => [
                'machineId' => $machineId,
                'currentMachineState' => $unknownMachineState,
                'expectedNewMachineState' => $findReceivedMachineState,
                'expectedEvent' => new MachineStateChangeEvent(
                    new Machine($machineId, $findReceivedMachineState, [], false),
                    'unknown'
                ),
            ],
        ];
    }
}
