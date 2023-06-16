<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\ResultsJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\Model\Job as ResultsClientJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

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

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
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
    public function eventSubscriptionsDataProvider(): array
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
        self::assertSame(0, $this->jobRepository->count([]));

        $jobId = md5((string) rand());

        $event = new ResultsJobCreatedEvent('authentication token', $jobId, \Mockery::mock(ResultsClientJob::class));

        $this->resultsJobFactory->createOnResultsJobCreatedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testCreateOnResultsJobCreatedEventSuccess(): void
    {
        $jobId = md5((string) rand());

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $this->jobRepository->add($job);

        self::assertSame(0, $this->resultsJobRepository->count([]));

        $resultsJobToken = md5((string) rand());
        $resultsJobState = 'awaiting-events';
        $resultsJobEndState = null;

        $resultsJob = new ResultsClientJob(
            $jobId,
            $resultsJobToken,
            new ResultsJobState($resultsJobState, $resultsJobEndState)
        );

        $event = new ResultsJobCreatedEvent('authentication token', $jobId, $resultsJob);

        $this->resultsJobFactory->createOnResultsJobCreatedEvent($event);

        $resultsJobEntity = $this->resultsJobRepository->find($jobId);
        self::assertEquals(
            new ResultsJob($jobId, $resultsJobToken, $resultsJobState, $resultsJobEndState),
            $resultsJobEntity
        );
    }
}
