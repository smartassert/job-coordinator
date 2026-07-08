<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteCreatedEvent;
use App\Model\MetaState;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\SerializedSuiteFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\SourcesClientSerializedSuiteFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\SourcesClient\Model\MetaState as SourcesClientMetaState;
use SmartAssert\SourcesClient\Model\SerializedSuite as SourcesSerializedSuite;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SerializedSuiteFactoryTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private SerializedSuiteRepository $serializedSuiteRepository;
    private SerializedSuiteFactory $serializedSuiteFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        foreach ($serializedSuiteRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->serializedSuiteRepository = $serializedSuiteRepository;

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $this->serializedSuiteFactory = $serializedSuiteFactory;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->serializedSuiteFactory::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            SerializedSuiteCreatedEvent::class => [
                'expectedListenedForEvent' => SerializedSuiteCreatedEvent::class,
                'expectedMethod' => 'createOnSerializedSuiteCreatedEvent',
            ],
        ];
    }

    public function testCreateOnSerializedSuiteCreatedEventNoJob(): void
    {
        $jobCount = $this->jobRepository->count([]);

        $jobId = Id::generate();
        $serializedSuiteId = Id::generate();
        $suiteId = Id::generate();

        $event = new SerializedSuiteCreatedEvent(
            $jobId,
            SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $suiteId)
        );

        $this->serializedSuiteFactory->createOnSerializedSuiteCreatedEvent($event);

        self::assertSame($jobCount, $this->jobRepository->count([]));
    }

    public function testCreateOnSerializedSuiteCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        self::assertSame(0, $this->serializedSuiteRepository->count([]));

        $serializedSuiteId = Id::generate();
        $suiteId = Id::generate();
        $serializedSuiteState = StringValue::random();

        $sourcesSerializedSuite = new SourcesSerializedSuite(
            $serializedSuiteId,
            $suiteId,
            [],
            $serializedSuiteState,
            new SourcesClientMetaState(false, false, true),
            null,
            null,
            [],
            [],
        );

        $event = new SerializedSuiteCreatedEvent($job->getId(), $sourcesSerializedSuite);

        $this->serializedSuiteFactory->createOnSerializedSuiteCreatedEvent($event);

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->getId());
        self::assertEquals(
            new SerializedSuite(
                $job->getId(),
                $serializedSuiteId,
                $serializedSuiteState,
                new MetaState(false, false, true),
            ),
            $serializedSuiteEntity
        );
    }
}
