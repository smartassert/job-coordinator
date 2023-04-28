<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Message\MachineStateChangeCheckMessage;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

abstract class AbstractCreateJobDispatchedMessagesTest extends AbstractCreateJobSuccessSetup
{
    public function testMachineStateChangeCheckMessageIsDispatched(): void
    {
        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $envelopes = $messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
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
}
