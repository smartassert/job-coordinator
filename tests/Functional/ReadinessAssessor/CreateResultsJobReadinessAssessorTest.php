<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\ResultsJob;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\CreateResultsJobReadinessHandler;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\JobFactory;
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

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
    }

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

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
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
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
