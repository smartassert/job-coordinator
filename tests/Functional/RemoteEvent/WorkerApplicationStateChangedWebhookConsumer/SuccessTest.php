<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\WorkerApplicationStateChangedWebhookConsumer;

use App\Enum\RemoteEventType;
use App\Event\WorkerJobRetrievedEvent;
use App\Tests\Functional\RemoteEvent\AbstractConsumerTestCase;
use App\Tests\Services\Factory\JobFactory;
use SmartAssert\WorkerClient\Factory\ApplicationStateFactory;
use SmartAssert\WorkerClient\Model\ApplicationState;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class SuccessTest extends AbstractConsumerTestCase
{
    public function testSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

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

        $applicationStateData = [
            'application' => $applicationAwaiting,
            'compilation' => $awaitingComponent,
            'execution' => $awaitingComponent,
            'event_delivery' => $awaitingComponent,
        ];

        $event = new RemoteEvent(
            name: RemoteEventType::WORKER_STATE_CHANGED->value,
            id: (string) new Ulid(),
            payload: [
                'job' => $job->getId(),
                'state' => $applicationStateData,
            ],
        );

        $response = self::$staticApplicationClient->makeWorkerApplicationStateStateChangedNotifyRequest(
            $this->remoteEventRequestFactory->createHeaders($event, $job->getNotifyToken()),
            $this->remoteEventRequestFactory->createBody($event, $job->getNotifyToken()),
        );

        self::assertSame(202, $response->getStatusCode());

        $applicationStateFactory = self::getContainer()->get(ApplicationStateFactory::class);
        \assert($applicationStateFactory instanceof ApplicationStateFactory);

        $expectedApplicationState = $applicationStateFactory->create($applicationStateData);
        \assert($expectedApplicationState instanceof ApplicationState);

        self::assertEquals(
            [
                new WorkerJobRetrievedEvent(
                    $job->getId(),
                    $expectedApplicationState,
                ),
            ],
            $this->eventRecorder->all(WorkerJobRetrievedEvent::class),
        );
    }
}
