<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Message\GetSerializedSuiteStateMessage;
use App\MessageDispatcher\SerializedSuiteStateChangeCheckMessageDispatcher;
use App\Services\UlidFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class SerializedSuiteStateChangeCheckMessageDispatcherTest extends WebTestCase
{
    public function testDispatch(): void
    {
        $dispatcher = self::getContainer()->get(SerializedSuiteStateChangeCheckMessageDispatcher::class);
        \assert($dispatcher instanceof SerializedSuiteStateChangeCheckMessageDispatcher);

        $authenticationToken = md5((string) rand());
        $serializedSuiteId = (new UlidFactory())->create();

        $message = new GetSerializedSuiteStateMessage($authenticationToken, $serializedSuiteId);

        $dispatcher->dispatch($message);

        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        $envelopes = $transport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $dispatchedEnvelope);
        self::assertEquals($message, $dispatchedEnvelope->getMessage());

        $delayStamps = $dispatchedEnvelope->all(DelayStamp::class);
        self::assertCount(1, $delayStamps);

        $delayStamp = $delayStamps[0];
        self::assertInstanceOf(DelayStamp::class, $delayStamp);

        $expectedDelay = self::getContainer()->getParameter(
            'serialized_suite_state_change_check_message_dispatch_delay'
        );
        \assert(is_int($expectedDelay));

        self::assertEquals(new DelayStamp($expectedDelay), $delayStamp);
    }
}
