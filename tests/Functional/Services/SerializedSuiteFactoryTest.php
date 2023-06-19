<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\SerializedSuiteFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite as SourcesSerializedSuite;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

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

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->serializedSuiteFactory);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->serializedSuiteFactory::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
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
        self::assertSame(0, $this->jobRepository->count([]));

        $jobId = md5((string) rand());

        $event = new SerializedSuiteCreatedEvent(
            'authentication token',
            $jobId,
            \Mockery::mock(SourcesSerializedSuite::class)
        );

        $this->serializedSuiteFactory->createOnSerializedSuiteCreatedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testCreateOnSerializedSuiteCreatedEventSuccess(): void
    {
        $jobId = md5((string) rand());

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $this->jobRepository->add($job);

        self::assertSame(0, $this->serializedSuiteRepository->count([]));

        $serializedSuiteId = md5((string) rand());
        $suiteId = md5((string) rand());
        $serializedSuiteState = md5((string) rand());

        $sourcesSerializedSuite = new SourcesSerializedSuite(
            $serializedSuiteId,
            $suiteId,
            [],
            $serializedSuiteState,
            null,
            null
        );

        $event = new SerializedSuiteCreatedEvent('authentication token', $jobId, $sourcesSerializedSuite);

        $this->serializedSuiteFactory->createOnSerializedSuiteCreatedEvent($event);

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($jobId);
        self::assertEquals(
            new SerializedSuite($jobId, $serializedSuiteId, $serializedSuiteState),
            $serializedSuiteEntity
        );
    }
}
