<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\ResultsJobStateChangedWebhookConsumer;

use App\Event\ResultsJobRetrievedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractConsumerTestCase
{
    #[DataProvider('noEventIsDispatchedDataProvider')]
    public function testNoEventIsDispatched(RemoteEvent $event): void
    {
        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->notifySecret);

        $response = self::$staticApplicationClient->makeResultsJobStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
        );

        self::assertSame(202, $response->getStatusCode());
        self::assertEquals([], $this->eventRecorder->all(ResultsJobRetrievedEvent::class));
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
            'event encapsulates non-job object' => [
                'event' => new RemoteEvent(
                    name: 'results.job.state_changed',
                    id: (string) new Ulid(),
                    payload: [
                        'id' => (string) new Ulid(),
                    ],
                ),
            ],
            'event encapsulates invalid job' => [
                'event' => new RemoteEvent(
                    name: 'results.job.state_changed',
                    id: (string) new Ulid(),
                    payload: [
                        'label' => (string) new Ulid(),
                        'state' => 'lifecycle/compilation-started',
                        'event_add_url' => (string) new Ulid(),
                        'has_events' => true,
                    ],
                ),
            ],
        ];
    }
}
