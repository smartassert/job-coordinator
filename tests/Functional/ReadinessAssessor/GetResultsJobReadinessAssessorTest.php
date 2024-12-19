<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\ResultsJob;
use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Model\JobInterface;
use App\ReadinessAssessor\GetResultsJobReadinessAssessor;
use App\Repository\ResultsJobRepository;
use App\Services\PreparationStateFactory;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetResultsJobReadinessAssessorTest extends WebTestCase
{
    /**
     * @param callable(JobInterface, ResultsJobRepository): void $setup
     * @param callable(JobInterface): PreparationStateFactory    $preparationStateFactoryCreator
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

        $setup($job, $resultsJobRepository);
        $jobPreparationInspector = $preparationStateFactoryCreator($job);

        $assessor = self::getContainer()->get(GetResultsJobReadinessAssessor::class);
        \assert($assessor instanceof GetResultsJobReadinessAssessor);

        $assessor = new GetResultsJobReadinessAssessor($resultsJobRepository, $jobPreparationInspector);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'results job does not exist' => [
                'setup' => function (): void {
                },
                'preparationStateFactoryCreator' => function (): PreparationStateFactory {
                    return \Mockery::mock(PreparationStateFactory::class);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'results job has end state' => [
                'setup' => function (JobInterface $job, ResultsJobRepository $resultsJobRepository): void {
                    $resultsJob = new ResultsJob($job->getId(), 'token', 'state', 'end-state');

                    $resultsJobRepository->save($resultsJob);
                },
                'preparationStateFactoryCreator' => function (): PreparationStateFactory {
                    return \Mockery::mock(PreparationStateFactory::class);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'job preparation has failed' => [
                'setup' => function (JobInterface $job, ResultsJobRepository $resultsJobRepository): void {
                    $resultsJob = new ResultsJob($job->getId(), 'token', 'state', null);

                    $resultsJobRepository->save($resultsJob);
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
        ];
    }
}
