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
     * @param callable(non-empty-string): Machine                  $machineCreator
     * @param callable(non-empty-string): Machine                  $expectedMachineCreator
     * @param callable(non-empty-string, non-empty-string): ?Event $expectedEventCreator
     */
    public function testInvoke(
        callable $machineCreator,
        callable $expectedMachineCreator,
        callable $expectedEventCreator,
    ): void {
        $authenticationToken = $this->authenticationConfiguration->getValidApiToken();
        $machineId = md5((string) rand());
        $machine = $machineCreator($machineId);
        $message = new MachineStateChangeCheckMessage($authenticationToken, $machine);

        ($this->handler)($message);

        $latestEvent = $this->eventRecorder->getLatest();

        self::assertEquals($expectedEventCreator($machineId, $authenticationToken), $latestEvent);

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new MachineStateChangeCheckMessage($authenticationToken, $expectedMachineCreator($machineId)),
            $envelope->getMessage()
        );
    }

    /**
     * @return array<mixed>
     */
    public function invokeDataProvider(): array
    {
        return [
            'no state change' => [
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
            'has state change' => [
                'machineCreator' => function (string $machineId) {
                    \assert('' !== $machineId);

                    return new Machine($machineId, 'unknown', 'unknown', []);
                },
                'expectedMachineCreator' => function (string $machineId) {
                    \assert('' !== $machineId);

                    return new Machine($machineId, 'find/received', 'finding', []);
                },
                'expectedEventCreator' => function (string $machineId, string $authenticationToken) {
                    \assert('' !== $machineId);
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent(
                        $authenticationToken,
                        new Machine($machineId, 'unknown', 'unknown', []),
                        new Machine($machineId, 'find/received', 'finding', []),
                    );
                },
            ],
        ];
    }
}
