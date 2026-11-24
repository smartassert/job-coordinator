<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\WorkerApplicationStateChangedWebhookConsumer;

use App\Enum\RemoteEventType;
use App\Event\WorkerJobRetrievedEvent;
use App\Model\JobInterface;
use App\Tests\Functional\RemoteEvent\AbstractConsumerTestCase;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractConsumerTestCase
{
    /**
     * @param callable(JobInterface): RemoteEvent $eventCreator
     * @param callable(JobInterface): string      $tokenCreator
     */
    #[DataProvider('noEventIsDispatchedDataProvider')]
    public function testNoEventIsDispatched(
        callable $eventCreator,
        callable $tokenCreator,
        int $expectedResponseStatusCode,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $event = $eventCreator($job);
        $token = $tokenCreator($job);

        $response = self::$staticApplicationClient->makeWorkerApplicationStateStateChangedNotifyRequest(
            $this->remoteEventRequestFactory->createHeaders($event, $token),
            $this->remoteEventRequestFactory->createBody($event, $token),
        );

        self::assertSame($expectedResponseStatusCode, $response->getStatusCode());
        self::assertEquals([], $this->eventRecorder->all(WorkerJobRetrievedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function noEventIsDispatchedDataProvider(): array
    {
        $awaitingComponent = [
            'state' => 'awaiting',
            'meta_state' => [
                'pending' => true,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
            ],
        ];

        $applicationAwaiting = [
            'state' => 'awaiting',
            'meta_state' => [
                'pending' => false,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
            ],
        ];

        return [
            'event name incorrect' => [
                'eventCreator' => function (
                    JobInterface $job,
                ) use (
                    $applicationAwaiting,
                    $awaitingComponent
                ): RemoteEvent {
                    return new RemoteEvent(
                        name: 'non_matching_event_name',
                        id: (string) new Ulid(),
                        payload: [
                            'job' => $job->getId(),
                            'state' => [
                                'application' => $applicationAwaiting,
                                'compilation' => $awaitingComponent,
                                'execution' => $awaitingComponent,
                                'event_delivery' => $awaitingComponent,
                            ],
                        ],
                    );
                },
                'tokenCreator' => fn (JobInterface $job) => $job->getNotifyToken(),
                'expectedResponseStatusCode' => 202,
            ],
            'invalid job' => [
                'eventCreator' => function () use ($applicationAwaiting, $awaitingComponent): RemoteEvent {
                    return new RemoteEvent(
                        name: RemoteEventType::WORKER_STATE_CHANGED->value,
                        id: (string) new Ulid(),
                        payload: [
                            'job' => 'invalid-job',
                            'state' => [
                                'application' => $applicationAwaiting,
                                'compilation' => $awaitingComponent,
                                'execution' => $awaitingComponent,
                                'event_delivery' => $awaitingComponent,
                            ],
                        ],
                    );
                },
                'tokenCreator' => fn (JobInterface $job) => $job->getNotifyToken(),
                'expectedResponseStatusCode' => 404,
            ],
            'invalid application state' => [
                'eventCreator' => function (JobInterface $job) use ($applicationAwaiting): RemoteEvent {
                    return new RemoteEvent(
                        name: RemoteEventType::WORKER_STATE_CHANGED->value,
                        id: (string) new Ulid(),
                        payload: [
                            'job' => $job->getId(),
                            'state' => [
                                'application' => $applicationAwaiting,
                            ],
                        ],
                    );
                },
                'tokenCreator' => fn (JobInterface $job) => $job->getNotifyToken(),
                'expectedResponseStatusCode' => 400,
            ],
            'invalid token' => [
                'eventCreator' => function (
                    JobInterface $job,
                ) use (
                    $applicationAwaiting,
                    $awaitingComponent,
                ): RemoteEvent {
                    return new RemoteEvent(
                        name: RemoteEventType::MACHINE_STATE_CHANGED->value,
                        id: (string) new Ulid(),
                        payload: [
                            'job' => $job->getId(),
                            'state' => [
                                'application' => $applicationAwaiting,
                                'compilation' => $awaitingComponent,
                                'execution' => $awaitingComponent,
                                'event_delivery' => $awaitingComponent,
                            ],
                        ],
                    );
                },
                'tokenCreator' => fn () => 'invalid token',
                'expectedResponseStatusCode' => 406,
            ],
        ];
    }
}
