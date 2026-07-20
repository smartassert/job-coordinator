<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\ResultsJobStateChangedWebhookConsumer;

use App\Event\ResultsJobRetrievedEvent;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use SmartAssert\ResultsClient\JobFactory as ResultsJobModelFactory;
use SmartAssert\ResultsClient\Model\Job as ResultsJobModel;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class SuccessTest extends AbstractConsumerTestCase
{
    public function testSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $resultsJobEntity = $resultsJobFactory->create($job);

        $resultsJobModelData = [
            'label' => $job->getId(),
            'state' => $resultsJobEntity->getState(),
            'event_add_url' => $resultsJobEntity->eventAddUrl,
            'has_events' => $resultsJobEntity->hasEvents(),
        ];

        $event = new RemoteEvent(
            name: 'results.job.state_changed',
            id: (string) new Ulid(),
            payload: $resultsJobModelData,
        );

        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->notifySecret);

        $response = self::$staticApplicationClient->makeResultsJobStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
        );

        self::assertSame(202, $response->getStatusCode());

        $resultsJobModelFactory = self::getContainer()->get(ResultsJobModelFactory::class);
        \assert($resultsJobModelFactory instanceof ResultsJobModelFactory);

        $resultsJobModel = $resultsJobModelFactory->create($resultsJobModelData);
        \assert($resultsJobModel instanceof ResultsJobModel);

        self::assertEquals(
            [
                new ResultsJobRetrievedEvent($resultsJobModel),
            ],
            $this->eventRecorder->all(ResultsJobRetrievedEvent::class),
        );
    }
}
