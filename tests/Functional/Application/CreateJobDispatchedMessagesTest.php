<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Message\CreateResultsJobMessage;
use App\Message\CreateSerializedSuiteMessage;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateJobDispatchedMessagesTest extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;

    /**
     * @var Envelope[]
     */
    private static array $envelopes;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);

        $envelopes = $messengerTransport->getSent();
        \assert(is_array($envelopes));
        self::$envelopes = $envelopes;
    }

    public function testDispatchedMessageCount(): void
    {
        self::assertCount(2, self::$envelopes);
    }

    #[DataProvider('messageIsDispatchedDataProvider')]
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
    public static function messageIsDispatchedDataProvider(): array
    {
        return [
            CreateResultsJobMessage::class => [
                'expectedMessageCreator' => function () {
                    $jobId = self::$createResponseData['id'] ?? null;
                    \assert(is_string($jobId));
                    \assert('' !== $jobId);

                    return new CreateResultsJobMessage(self::$apiToken, $jobId);
                },
            ],
            CreateSerializedSuiteMessage::class => [
                'expectedMessageCreator' => function () {
                    $jobId = self::$createResponseData['id'] ?? null;
                    \assert(is_string($jobId));
                    \assert('' !== $jobId);

                    return new CreateSerializedSuiteMessage(self::$apiToken, $jobId, []);
                },
            ],
        ];
    }
}
