<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\ResultsJobMutator;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResultsJobMutatorTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private ResultsJobRepository $resultsJobRepository;
    private ResultsJobMutator $resultsJobMutator;

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

        $resultsJobMutator = self::getContainer()->get(ResultsJobMutator::class);
        \assert($resultsJobMutator instanceof ResultsJobMutator);
        $this->resultsJobMutator = $resultsJobMutator;
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->resultsJobMutator::getSubscribedEvents();
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
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'setState',
            ],
        ];
    }

    /**
     * @dataProvider setStateSuccessDataProvider
     *
     * @param callable(non-empty-string, JobRepository): ?Job $jobCreator
     * @param callable(?Job, ResultsJobRepository): void      $resultsJobCreator
     * @param callable(?Job): ResultsJobStateRetrievedEvent   $eventCreator
     * @param callable(?Job): ?ResultsJob                     $expectedResultsJobCreator
     */
    public function testSetStateSuccess(
        callable $jobCreator,
        callable $resultsJobCreator,
        callable $eventCreator,
        callable $expectedResultsJobCreator,
    ): void {
        $jobId = md5((string) rand());

        $job = $jobCreator($jobId, $this->jobRepository);
        $resultsJobCreator($job, $this->resultsJobRepository);

        $event = $eventCreator($job);

        $this->resultsJobMutator->setState($event);

        $resultsJob = $this->resultsJobRepository->find($jobId);
        self::assertEquals($expectedResultsJobCreator($job), $resultsJob);
    }

    /**
     * @return array<mixed>
     */
    public function setStateSuccessDataProvider(): array
    {
        $resultsJobToken = md5((string) rand());
        $jobCreator = function (string $jobId, JobRepository $jobRepository) {
            \assert('' !== $jobId);

            $job = new Job($jobId, 'user id', 'suite id', 600);
            $jobRepository->add($job);

            return $job;
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'resultsJobCreator' => function () {
                },
                'eventCreator' => function () {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        md5((string) rand()),
                        new ResultsJobState('awaiting-events', null),
                    );
                },
                'expectedResultsJobCreator' => function () {
                    return null;
                },
            ],
            'no results job' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function () {
                },
                'eventCreator' => function (Job $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new ResultsJobState('awaiting-events', null),
                    );
                },
                'expectedResultsJobCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function (
                    Job $job,
                    ResultsJobRepository $resultsJobRepository
                ) use (
                    $resultsJobToken
                ) {
                    $resultsJob = new ResultsJob($job->id, $resultsJobToken, 'awaiting-events', null);
                    $resultsJobRepository->save($resultsJob);
                },
                'eventCreator' => function (Job $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new ResultsJobState('awaiting-events', null),
                    );
                },
                'expectedResultsJobCreator' => function (Job $job) use ($resultsJobToken) {
                    return new ResultsJob($job->id, $resultsJobToken, 'awaiting-events', null);
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function (
                    Job $job,
                    ResultsJobRepository $resultsJobRepository
                ) use (
                    $resultsJobToken
                ) {
                    $resultsJob = new ResultsJob($job->id, $resultsJobToken, 'awaiting-events', null);
                    $resultsJobRepository->save($resultsJob);
                },
                'eventCreator' => function (Job $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new ResultsJobState('complete', 'ended'),
                    );
                },
                'expectedResultsJobCreator' => function (Job $job) use ($resultsJobToken) {
                    return new ResultsJob($job->id, $resultsJobToken, 'complete', 'ended');
                },
            ],
        ];
    }
}
