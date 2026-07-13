<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Functional\Application\GetClientAdapterTrait;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\RemoteEventConfigurationFactory;
use App\Tests\Services\Factory\SerializedSuiteFactory;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class NoEventDispatchedTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    private RemoteEventConfigurationFactory $remoteEventConfigurationFactory;
    private string $sourcesNotifySecret;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteEventConfigurationFactory = self::getContainer()->get(RemoteEventConfigurationFactory::class);
        \assert($remoteEventConfigurationFactory instanceof RemoteEventConfigurationFactory);
        $this->remoteEventConfigurationFactory = $remoteEventConfigurationFactory;

        $sourcesNotifySecret = self::getContainer()->getParameter('sources_notify_secret');
        \assert(is_string($sourcesNotifySecret));
        $this->sourcesNotifySecret = $sourcesNotifySecret;
    }

    public function testRemoteEventNameDoesNotMatch(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteEntityFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteEntityFactory instanceof SerializedSuiteFactory);

        $serializedSuite = $serializedSuiteEntityFactory->createNewForJob($job, 'requested');

        $event = new RemoteEvent(
            name: 'non_matching_event_name',
            id: (string) new Ulid(),
            payload: [],
        );

        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->sourcesNotifySecret);

        $response = self::$staticApplicationClient->makeSourcesSerializedSuiteStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
        );

        self::assertSame(202, $response->getStatusCode());

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $retrievedSerializedSuite = $serializedSuiteRepository->findOneBy(['id' => $serializedSuite->id]);
        self::assertSame($serializedSuite, $retrievedSerializedSuite);
    }

    public function testRemoteEventReferencesInvalidJobId(): void
    {
        $serializedSuiteEntityFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteEntityFactory instanceof SerializedSuiteFactory);

        $event = new RemoteEvent(
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
        );

        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->sourcesNotifySecret);

        $response = self::$staticApplicationClient->makeSourcesSerializedSuiteStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
        );

        self::assertSame(202, $response->getStatusCode());

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        self::assertEquals([], $eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }
}
