<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\ReadinessAssessor\CreateWorkerJobReadinessAssessor;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class CreateWorkerJobReadinessAssessorTest extends WebTestCase
{
    /**
     * @param callable(JobInterface, SerializedSuiteRepository, ResultsJobRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $setup($job, $serializedSuiteRepository, $resultsJobRepository);

        $assessor = self::getContainer()->get(CreateWorkerJobReadinessAssessor::class);
        \assert($assessor instanceof CreateWorkerJobReadinessAssessor);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'serialized suite does not exist' => [
                'setup' => function (): void {
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite is not prepared' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'preparing',
                        false,
                        false,
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not exist' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        true,
                        true,
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite preparation has failed' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobRepository $resultsJobRepository,
                ): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'failed',
                        false,
                        true,
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'state', null)
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobRepository $resultsJobRepository,
                ): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        true,
                        true,
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'state', null)
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
