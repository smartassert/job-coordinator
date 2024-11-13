<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Repository\ResultsJobRepository;
use App\Services\ResultsJobMutator;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class ResultsJobMutatorTest extends WebTestCase
{
    private ResultsJobRepository $resultsJobRepository;
    private ResultsJobMutator $resultsJobMutator;
    private JobFactory $jobFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->jobFactory = $jobFactory;

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

    #[DataProvider('eventSubscriptionsDataProvider')]
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
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            ResultsJobStateRetrievedEvent::class => [
                'expectedListenedForEvent' => ResultsJobStateRetrievedEvent::class,
                'expectedMethod' => 'setState',
            ],
        ];
    }

    /**
     * @param callable(JobFactory): ?Job                    $jobCreator
     * @param callable(?Job, ResultsJobRepository): void    $resultsJobCreator
     * @param callable(?Job): ResultsJobStateRetrievedEvent $eventCreator
     * @param callable(?Job): ?ResultsJob                   $expectedResultsJobCreator
     */
    #[DataProvider('setStateSuccessDataProvider')]
    public function testSetStateSuccess(
        callable $jobCreator,
        callable $resultsJobCreator,
        callable $eventCreator,
        callable $expectedResultsJobCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);
        $resultsJobCreator($job, $this->resultsJobRepository);

        $event = $eventCreator($job);

        $this->resultsJobMutator->setState($event);

        $resultsJob = null === $job
            ? null
            : $this->resultsJobRepository->find($job->id);

        self::assertEquals($expectedResultsJobCreator($job), $resultsJob);
    }

    /**
     * @return array<mixed>
     */
    public static function setStateSuccessDataProvider(): array
    {
        $resultsJobToken = md5((string) rand());
        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'resultsJobCreator' => function () {
                },
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $jobId,
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
                    \assert('' !== $job->id);

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
                    \assert('' !== $job->id);

                    $resultsJob = new ResultsJob($job->id, $resultsJobToken, 'awaiting-events', null);
                    $resultsJobRepository->save($resultsJob);
                },
                'eventCreator' => function (Job $job) {
                    \assert('' !== $job->id);

                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new ResultsJobState('awaiting-events', null),
                    );
                },
                'expectedResultsJobCreator' => function (Job $job) use ($resultsJobToken) {
                    \assert('' !== $job->id);

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
                    \assert('' !== $job->id);

                    $resultsJob = new ResultsJob($job->id, $resultsJobToken, 'awaiting-events', null);
                    $resultsJobRepository->save($resultsJob);
                },
                'eventCreator' => function (Job $job) {
                    \assert('' !== $job->id);

                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->id,
                        new ResultsJobState('complete', 'ended'),
                    );
                },
                'expectedResultsJobCreator' => function (Job $job) use ($resultsJobToken) {
                    \assert('' !== $job->id);

                    return new ResultsJob($job->id, $resultsJobToken, 'complete', 'ended');
                },
            ],
        ];
    }
}
