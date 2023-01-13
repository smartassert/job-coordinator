<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageHandler\MachineStateChangeCheckMessageHandler;
use App\Tests\Services\AuthenticationConfiguration;
use App\Tests\Services\EventSubscriber\EventRecorder;
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
     */
    public function testInvokeFoo(
        string $currentMachineState,
        string $expectedNewMachineState,
        ?Event $expectedEvent,
    ): void {
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();
        $machineId = md5((string) rand());
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
        return [
            'no state change' => [
                'currentMachineState' => 'find/received',
                'expectedNewMachineState' => 'find/received',
                'expectedEvent' => null,
            ],
            'has state change' => [
                'currentMachineState' => 'unknown',
                'expectedNewMachineState' => 'find/received',
                'expectedEvent' => new MachineStateChangeEvent('unknown', 'find/received'),
            ],
        ];
    }
}
