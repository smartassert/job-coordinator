<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\GetResultsJobReadinessHandler;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\PreparationStateFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetResultsJobReadinessAssessorTest extends WebTestCase
{
    public function testHandles(): void
    {
        $assessor = self::getContainer()->get(GetResultsJobReadinessHandler::class);
        \assert($assessor instanceof GetResultsJobReadinessHandler);

        self::assertTrue($assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));

        self::assertFalse($assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($assessor->handles(RemoteRequestType::createForMachineTermination()));
    }

    /**
     * @param callable(JobInterface, ResultsJobFactory, MachineRepository): void $setup
     * @param callable(JobInterface): PreparationStateFactory                    $preparationStateFactoryCreator
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(
        callable $setup,
        callable $preparationStateFactoryCreator,
        MessageHandlingReadiness $expected
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $setup($job, $resultsJobFactory, $machineRepository);
        $jobPreparationInspector = $preparationStateFactoryCreator($job);

        $assessor = new GetResultsJobReadinessHandler(
            $resultsJobRepository,
            $jobPreparationInspector,
            $machineRepository
        );

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'results job does not exist' => [
                'setup' => function (): void {},
                'preparationStateFactoryCreator' => function (): PreparationStateFactory {
                    return \Mockery::mock(PreparationStateFactory::class);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'results job has end state' => [
                'setup' => function (JobInterface $job, ResultsJobFactory $resultsJobFactory): void {
                    $resultsJobFactory->create(
                        job: $job,
                        endState: 'end-state',
                        metaState: new MetaState(true, false),
                    );
                },
                'preparationStateFactoryCreator' => function (): PreparationStateFactory {
                    return \Mockery::mock(PreparationStateFactory::class);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'job preparation has failed' => [
                'setup' => function (JobInterface $job, ResultsJobFactory $resultsJobFactory): void {
                    $resultsJobFactory->create($job);
                },
                'preparationStateFactoryCreator' => function (JobInterface $job): PreparationStateFactory {
                    $factory = \Mockery::mock(PreparationStateFactory::class);
                    $factory
                        ->shouldReceive('createState')
                        ->with($job->getId())
                        ->andReturn(PreparationState::FAILED)
                    ;

                    return $factory;
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'machine is null' => [
                'setup' => function (JobInterface $job, ResultsJobFactory $resultsJobFactory): void {
                    $resultsJobFactory->create($job);
                },
                'preparationStateFactoryCreator' => function (JobInterface $job): PreparationStateFactory {
                    $factory = \Mockery::mock(PreparationStateFactory::class);
                    $factory
                        ->shouldReceive('createState')
                        ->with($job->getId())
                        ->andReturn(PreparationState::SUCCEEDED)
                    ;

                    return $factory;
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'ready' => [
                'setup' => function (
                    JobInterface $job,
                    ResultsJobFactory $resultsJobFactory,
                    MachineRepository $machineRepository
                ): void {
                    $resultsJobFactory->create($job);

                    $machine = new Machine($job->getId(), 'up/active', 'up', false, false);
                    $machineRepository->save($machine);
                },
                'preparationStateFactoryCreator' => function (JobInterface $job): PreparationStateFactory {
                    $factory = \Mockery::mock(PreparationStateFactory::class);
                    $factory
                        ->shouldReceive('createState')
                        ->with($job->getId())
                        ->andReturn(PreparationState::SUCCEEDED)
                    ;

                    return $factory;
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
