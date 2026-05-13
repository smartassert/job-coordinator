<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateResultsJobReadinessAssessorTest extends WebTestCase
{
    private CreateResultsJobReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(CreateResultsJobReadinessAssessor::class);
        \assert($assessor instanceof CreateResultsJobReadinessAssessor);

        $this->assessor = $assessor;
    }

    /**
     * @param callable(JobInterface, ResultsJobFactory): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $setup($job, $resultsJobFactory);

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'results job already exists' => [
                'setup' => function (JobInterface $job, ResultsJobFactory $resultsJobFactory): void {
                    $resultsJobFactory->create($job);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'ready' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
