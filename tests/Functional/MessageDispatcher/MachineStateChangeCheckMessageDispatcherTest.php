<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class MachineStateChangeCheckMessageDispatcherTest extends WebTestCase
{
    public function testFoo(): void
    {
        $dispatcher = self::getContainer()->get(MachineStateChangeCheckMessageDispatcher::class);
        \assert($dispatcher instanceof MachineStateChangeCheckMessageDispatcher);

        $authenticationToken = md5((string) rand());
        $machineId = md5((string) rand());

        $dispatcher->createAndDispatch($authenticationToken, $machineId);

        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        $envelopes = $transport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals(
            new MachineStateChangeCheckMessage($authenticationToken, $machineId, null),
            $dispatchedEnvelope->getMessage()
        );

        $delayStamps = $dispatchedEnvelope->all(DelayStamp::class);
        self::assertCount(1, $delayStamps);

        $delayStamp = $delayStamps[0];
        self::assertInstanceOf(DelayStamp::class, $delayStamp);

        $expectedDelay = self::getContainer()->getParameter('machine_state_change_check_message_dispatch_delay');
        \assert(is_int($expectedDelay));

        self::assertEquals(new DelayStamp($expectedDelay), $delayStamp);
    }
}