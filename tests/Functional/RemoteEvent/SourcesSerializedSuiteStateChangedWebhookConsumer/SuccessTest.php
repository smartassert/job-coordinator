<?php

declare(strict_types=1);

namespace App\Tests\Functional\RemoteEvent\SourcesSerializedSuiteStateChangedWebhookConsumer;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Functional\Application\GetClientAdapterTrait;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\RemoteEventConfigurationFactory;
use App\Tests\Services\Factory\SerializedSuiteFactory;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteFactory as SerializedSuiteModelFactory;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Ulid;

class SuccessTest extends AbstractApplicationTest
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

    public function testSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteEntityFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteEntityFactory instanceof SerializedSuiteFactory);

        $serializedSuite = $serializedSuiteEntityFactory->createNewForJob($job, 'requested');

        $serializedSuiteModelData = [
            'id' => $serializedSuite->id,
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
        ];

        $event = new RemoteEvent(
            name: 'sources.serialized_suite.state_changed',
            id: (string) new Ulid(),
            payload: $serializedSuiteModelData,
        );

        $remoteEventConfiguration = $this->remoteEventConfigurationFactory->create($event, $this->sourcesNotifySecret);

        $response = self::$staticApplicationClient->makeSourcesSerializedSuiteStateChangedNotifyRequest(
            $remoteEventConfiguration->headers,
            $remoteEventConfiguration->body,
        );

        self::assertSame(202, $response->getStatusCode());

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        $serializedSuiteModelFactory = self::getContainer()->get(SerializedSuiteModelFactory::class);
        \assert($serializedSuiteModelFactory instanceof SerializedSuiteModelFactory);

        $serializedSuiteModel = $serializedSuiteModelFactory->create($serializedSuiteModelData);
        \assert($serializedSuiteModel instanceof SerializedSuiteModel);

        self::assertEquals(
            [
                new SerializedSuiteRetrievedEvent($job->getId(), $serializedSuiteModel),
            ],
            $eventRecorder->all(SerializedSuiteRetrievedEvent::class),
        );
    }
}
