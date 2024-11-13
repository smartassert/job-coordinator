<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\ResultsJobFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\Job as ResultsClientJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class ResultsJobFactoryTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private ResultsJobRepository $resultsJobRepository;
    private ResultsJobFactory $resultsJobFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        foreach ($resultsJobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->resultsJobRepository = $resultsJobRepository;

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $this->resultsJobFactory = $resultsJobFactory;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->resultsJobFactory::getSubscribedEvents();
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
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                'expectedListenedForEvent' => ResultsJobCreatedEvent::class,
                'expectedMethod' => 'createOnResultsJobCreatedEvent',
            ],
        ];
    }

    public function testCreateOnResultsJobCreatedEventNoJob(): void
    {
        $jobCount = $this->jobRepository->count([]);
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $event = new ResultsJobCreatedEvent(
            'authentication token',
            $jobId,
            ResultsClientJobFactory::createRandom()
        );

        $this->resultsJobFactory->createOnResultsJobCreatedEvent($event);

        self::assertSame($jobCount, $this->jobRepository->count([]));
    }

    public function testCreateOnResultsJobCreatedEventSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->id);

        self::assertSame(0, $this->resultsJobRepository->count([]));

        $resultsJobToken = md5((string) rand());
        $resultsJobState = 'awaiting-events';
        $resultsJobEndState = null;

        $resultsJob = new ResultsClientJob(
            $job->id,
            $resultsJobToken,
            new ResultsJobState($resultsJobState, $resultsJobEndState)
        );

        $event = new ResultsJobCreatedEvent('authentication token', $job->id, $resultsJob);

        $this->resultsJobFactory->createOnResultsJobCreatedEvent($event);

        $resultsJobEntity = $this->resultsJobRepository->find($job->id);
        self::assertEquals(
            new ResultsJob($job->id, $resultsJobToken, $resultsJobState, $resultsJobEndState),
            $resultsJobEntity
        );
    }
}
