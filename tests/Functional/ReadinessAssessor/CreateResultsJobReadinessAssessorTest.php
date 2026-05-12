<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\CreateResultsJobMessage;
use App\Model\JobInterface;
use App\ReadinessAssessor\CreateResultsJobReadinessHandler;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreateResultsJobReadinessAssessorTest extends WebTestCase
{
    private CreateResultsJobReadinessHandler $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(CreateResultsJobReadinessHandler::class);
        \assert($assessor instanceof CreateResultsJobReadinessHandler);

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

        $message = new CreateResultsJobMessage('authentication-token', $job->getId());

        self::assertSame($expected, $this->assessor->isReady($message));
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
