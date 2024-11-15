<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class CreateMachineReadinessAssessorTest extends WebTestCase
{
    /**
     * @param callable(JobInterface, MachineRepository, SerializedSuiteRepository, ResultsJobRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $setup($job, $machineRepository, $serializedSuiteRepository, $resultsJobRepository);

        $assessor = self::getContainer()->get(CreateMachineReadinessAssessor::class);
        \assert($assessor instanceof CreateMachineReadinessAssessor);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'machine already exists' => [
                'setup' => function (JobInterface $job, MachineRepository $machineRepository): void {
                    $machineRepository->save(
                        new Machine($job->getId(), 'state', 'state-category', false)
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'serialized suite does not exist' => [
                'setup' => function (): void {
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite is not prepared' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    SerializedSuiteRepository $serializedSuiteRepository
                ): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'preparing', false, false)
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not exist' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    SerializedSuiteRepository $serializedSuiteRepository
                ): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true)
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    MachineRepository $machineRepository,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobRepository $resultsJobRepository
                ): void {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true)
                    );

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'compiling', null)
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
