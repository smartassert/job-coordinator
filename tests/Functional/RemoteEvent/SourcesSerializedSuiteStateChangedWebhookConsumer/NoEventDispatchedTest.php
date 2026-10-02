<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Event\SerializedSuiteRetrievedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractConsumerTestCase
{
    #[DataProvider('noEventIsDispatchedDataProvider')]
    public function testNoEventIsDispatched(RemoteEvent $event): void
    {
        $response = self::$staticApplicationClient->makeSourcesSerializedSuiteStateChangedNotifyRequest(
            $this->remoteEventRequestFactory->createHeaders($event, $this->notifySecret),
            $this->remoteEventRequestFactory->createBody($event, $this->notifySecret),
        );

        self::assertSame(202, $response->getStatusCode());
        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function noEventIsDispatchedDataProvider(): array
    {
        return [
            'event name incorrect' => [
                'event' => new RemoteEvent(
                    name: 'non_matching_event_name',
                    id: (string) new Ulid(),
                    payload: [],
                ),
            ],
            'event references invalid job' => [
                'event' => new RemoteEvent(
                    name: 'sources.serialized_suite.state_changed',
                    id: (string) new Ulid(),
                    payload: [
                        'id' => (string) new Ulid(),
                        'suite_id' => (string) new Ulid(),
                        'parameters' => [],
                        'state' => 'requested',
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [],
                        'next_states' => [
                            'preparing/running',
                            'preparing/preparing',
                            'prepared',
                            'failed',
                        ],
                    ],
                ),
            ],
        ];
    }
}
