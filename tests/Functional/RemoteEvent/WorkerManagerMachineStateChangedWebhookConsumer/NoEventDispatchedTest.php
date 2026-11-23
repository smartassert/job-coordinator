<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\WorkerManagerMachineStateChangedWebhookConsumer;

use App\Enum\RemoteEventType;
use App\Event\ResultsJobRetrievedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractConsumerTestCase
{
    #[DataProvider('noEventIsDispatchedDataProvider')]
    public function testNoEventIsDispatched(RemoteEvent $event): void
    {
        $response = self::$staticApplicationClient->makeWorkerManagerMachineStateChangedNotifyRequest(
            $this->remoteEventRequestFactory->createHeaders($event, $this->notifySecret),
            $this->remoteEventRequestFactory->createBody($event, $this->notifySecret),
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
            'event encapsulates non-machine object' => [
                'event' => new RemoteEvent(
                    name: RemoteEventType::MACHINE_STATE_CHANGED->value,
                    id: (string) new Ulid(),
                    payload: [
                        'id' => (string) new Ulid(),
                    ],
                ),
            ],
        ];
    }
}
