<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Message\GetSerializedSuiteStateMessage;
use App\Message\MachineStateChangeCheckMessage;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

abstract class AbstractCreateJobDispatchedMessagesTest extends AbstractCreateJobSuccessSetup
{
    /**
     * @var array<mixed>
     */
    private static array $envelopes;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $envelopes = $messengerTransport->get();
        \assert(is_array($envelopes));
        self::$envelopes = $envelopes;
    }

    public function testDispatchedMessageCount(): void
    {
        self::assertCount(2, self::$envelopes);
    }

    public function testMachineStateChangeCheckMessageIsDispatched(): void
    {
        $envelope = self::$envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);

        $machineData = self::$createResponseData['machine'] ?? [];
        self::assertIsArray($machineData);

        $expectedMachineStateChangeCheckMessage = new MachineStateChangeCheckMessage(
            self::$apiToken,
            new Machine(
                $machineData['id'],
                $machineData['state'],
                $machineData['state_category'],
                $machineData['ip_addresses']
            )
        );

        self::assertEquals($expectedMachineStateChangeCheckMessage, $envelope->getMessage());
    }

    public function testGetSerializedSuiteStateMessageIsDispatched(): void
    {
        $envelope = self::$envelopes[1];
        self::assertInstanceOf(Envelope::class, $envelope);

        $jobData = self::$createResponseData['job'] ?? [];
        \assert(is_array($jobData));

        $serializedSuiteId = $jobData['serialized_suite_id'] ?? null;
        \assert(is_string($serializedSuiteId));
        \assert('' !== $serializedSuiteId);

        self::assertEquals(
            new GetSerializedSuiteStateMessage(self::$apiToken, $serializedSuiteId),
            $envelope->getMessage()
        );
    }
}
