<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\ResultsJobRepository;
use App\Services\ResultsJobMutator;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
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
     * @param callable(JobFactory): ?JobInterface                    $jobCreator
     * @param callable(?JobInterface, ResultsJobFactory): void       $resultsJobCreator
     * @param callable(?JobInterface): ResultsJobStateRetrievedEvent $eventCreator
     * @param callable(?JobInterface): ?ResultsJob                   $expectedResultsJobCreator
     */
    #[DataProvider('setStateSuccessDataProvider')]
    public function testSetStateSuccess(
        callable $jobCreator,
        callable $resultsJobCreator,
        callable $eventCreator,
        callable $expectedResultsJobCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobCreator($job, $resultsJobFactory);

        $event = $eventCreator($job);

        $this->resultsJobMutator->setState($event);

        $resultsJob = null === $job
            ? null
            : $this->resultsJobRepository->find($job->getId());

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
                'resultsJobCreator' => function () {},
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();

                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $jobId,
                        new ResultsJobState('awaiting-events', null, new ResultsClientMetaState(false, false)),
                    );
                },
                'expectedResultsJobCreator' => function () {
                    return null;
                },
            ],
            'no results job' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function () {},
                'eventCreator' => function (JobInterface $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        new ResultsJobState('awaiting-events', null, new ResultsClientMetaState(false, false)),
                    );
                },
                'expectedResultsJobCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function (
                    JobInterface $job,
                    ResultsJobFactory $resultsJobFactory
                ) use (
                    $resultsJobToken
                ) {
                    $resultsJobFactory->create(job: $job, token: $resultsJobToken, state: 'awaiting-events');
                },
                'eventCreator' => function (JobInterface $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        new ResultsJobState('awaiting-events', null, new ResultsClientMetaState(false, false)),
                    );
                },
                'expectedResultsJobCreator' => function (JobInterface $job) use ($resultsJobToken) {
                    return new ResultsJob(
                        $job->getId(),
                        $resultsJobToken,
                        'awaiting-events',
                        null,
                        new MetaState(false, false),
                    );
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'resultsJobCreator' => function (
                    JobInterface $job,
                    ResultsJobFactory $resultsJobFactory
                ) use (
                    $resultsJobToken
                ) {
                    $resultsJobFactory->create(job: $job, token: $resultsJobToken, state: 'awaiting-events');
                },
                'eventCreator' => function (JobInterface $job) {
                    return new ResultsJobStateRetrievedEvent(
                        md5((string) rand()),
                        $job->getId(),
                        new ResultsJobState('complete', 'ended', new ResultsClientMetaState(true, true)),
                    );
                },
                'expectedResultsJobCreator' => function (JobInterface $job) use ($resultsJobToken) {
                    return new ResultsJob(
                        $job->getId(),
                        $resultsJobToken,
                        'complete',
                        'ended',
                        new MetaState(true, true),
                    );
                },
            ],
        ];
    }
}
