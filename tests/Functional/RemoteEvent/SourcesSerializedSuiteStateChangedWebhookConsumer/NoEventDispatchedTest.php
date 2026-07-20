<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Functional\Application\GetClientAdapterTrait;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\RemoteEventConfigurationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    private EventRecorder $eventRecorder;
    private RemoteEventConfigurationFactory $remoteEventConfigurationFactory;
    private string $sourcesNotifySecret;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $remoteEventConfigurationFactory = self::getContainer()->get(RemoteEventConfigurationFactory::class);
        \assert($remoteEventConfigurationFactory instanceof RemoteEventConfigurationFactory);
        $this->remoteEventConfigurationFactory = $remoteEventConfigurationFactory;

        $sourcesNotifySecret = self::getContainer()->getParameter('sources_notify_secret');
        \assert(is_string($sourcesNotifySecret));
        $this->sourcesNotifySecret = $sourcesNotifySecret;
    }

    #[DataProvider('noEventIsDispatchedDataProvider')]
    public function testNoEventIsDispatched(RemoteEvent $event): void
    {
        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->sourcesNotifySecret);

        $response = self::$staticApplicationClient->makeSourcesSerializedSuiteStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
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
