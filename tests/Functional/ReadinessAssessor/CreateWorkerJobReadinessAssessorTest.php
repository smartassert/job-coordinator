<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Message\CreateWorkerJobMessage;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\CreateWorkerJobReadinessAssessor;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class CreateWorkerJobReadinessAssessorTest extends WebTestCase
{
    private CreateWorkerJobReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(CreateWorkerJobReadinessAssessor::class);
        \assert($assessor instanceof CreateWorkerJobReadinessAssessor);

        $this->assessor = $assessor;
    }

    /**
     * @param callable(JobInterface, SerializedSuiteRepository, ResultsJobFactory): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $setup($job, $serializedSuiteRepository, $resultsJobFactory);

        $message = new CreateWorkerJobMessage(
            'authentication-token',
            $job->getId(),
            600,
            '127.0.0.1',
        );

        self::assertSame($expected, $this->assessor->isReady($message));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'serialized suite does not exist' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite is not prepared' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'preparing',
                        new MetaState(false, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not exist' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true),
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite preparation has failed' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobFactory $resultsJobFactory,
                ): void {
                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'failed',
                        new MetaState(true, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobFactory $resultsJobFactory,
                ): void {
                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
