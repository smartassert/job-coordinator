<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ReadinessAssessor;

use App\Entity\ResultsJob;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Repository\ResultsJobRepository;
use App\Services\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateResultsJobReadinessAssessorTest extends WebTestCase
{
    /**
     * @param callable(JobInterface, ResultsJobRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $setup($job, $resultsJobRepository);

        $assessor = self::getContainer()->get(CreateResultsJobReadinessAssessor::class);
        \assert($assessor instanceof CreateResultsJobReadinessAssessor);

        self::assertSame($expected, $assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'results job already exists' => [
                'setup' => function (JobInterface $job, ResultsJobRepository $resultsJobRepository): void {
                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'state', null)
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (): void {
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
