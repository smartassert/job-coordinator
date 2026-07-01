<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\CreateWorkerJobReadinessAssessor;
use App\Repository\MachineRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Generator\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
     * @param callable(JobInterface, SerializedSuiteRepository, ResultsJobFactory, MachineRepository): void $setup
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

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $setup($job, $serializedSuiteRepository, $resultsJobFactory, $machineRepository);

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
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
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'preparing',
                        new MetaState(false, false, true),
                    );

                    $serializedSuiteRepository->save($serializedSuite);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'results job does not exist' => [
                'setup' => function (JobInterface $job, SerializedSuiteRepository $serializedSuiteRepository): void {
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true, false),
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
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'failed',
                        new MetaState(true, false, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'machine does not exist' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobFactory $resultsJobFactory,
                ): void {
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'machine is not active' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobFactory $resultsJobFactory,
                    MachineRepository $machineRepository,
                ): void {
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);

                    $machine = new Machine($job->getId(), 'find/finding', 'pre_active');
                    $machineRepository->save($machine);
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'is ready' => [
                'setup' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    ResultsJobFactory $resultsJobFactory,
                    MachineRepository $machineRepository,
                ): void {
                    $serializedSuiteId = Id::generate();

                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        $serializedSuiteId,
                        'prepared',
                        new MetaState(true, true, false),
                    );

                    $serializedSuiteRepository->save($serializedSuite);

                    $resultsJobFactory->create($job);

                    $machine = new Machine($job->getId(), 'up/acive', 'active');
                    $machine->setIsActive();
                    $machineRepository->save($machine);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
