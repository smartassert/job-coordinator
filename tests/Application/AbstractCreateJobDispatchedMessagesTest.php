<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Message\CreateResultsJobMessage;
use App\Message\CreateSerializedSuiteMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

abstract class AbstractCreateJobDispatchedMessagesTest extends AbstractCreateJobSuccessSetup
{
    /**
     * @var Envelope[]
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

    /**
     * @dataProvider messageIsDispatchedDataProvider
     */
    public function testMessageIsDispatched(callable $expectedMessageCreator): void
    {
        $expectedMessage = $expectedMessageCreator();

        $messageIsFound = false;
        foreach (self::$envelopes as $envelope) {
            if ($envelope->getMessage()::class === $expectedMessage::class) {
                $messageIsFound = true;
                self::assertEquals($expectedMessage, $envelope->getMessage());
            }
        }

        if (false === $messageIsFound) {
            self::fail('Message "' . $expectedMessage::class . '" not found.');
        }
    }

    /**
     * @return array<mixed>
     */
    public function messageIsDispatchedDataProvider(): array
    {
        return [
            CreateResultsJobMessage::class => [
                'expectedMessageCreator' => function () {
                    $jobId = self::$createResponseData['id'] ?? null;
                    \assert(is_string($jobId));
                    \assert('' !== $jobId);

                    return new CreateResultsJobMessage(self::$apiToken, $jobId, 0);
                },
            ],
            CreateSerializedSuiteMessage::class => [
                'expectedMessageCreator' => function () {
                    $jobId = self::$createResponseData['id'] ?? null;
                    \assert(is_string($jobId));
                    \assert('' !== $jobId);

                    return new CreateSerializedSuiteMessage(self::$apiToken, $jobId, 0, []);
                },
            ],
        ];
    }
}
