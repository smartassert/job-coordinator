<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\CreateWorkerJobReadinessHandler;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class CreateWorkerJobReadinessAssessorTest extends WebTestCase
{
    private CreateWorkerJobReadinessHandler $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(CreateWorkerJobReadinessHandler::class);
        \assert($assessor instanceof CreateWorkerJobReadinessHandler);

        $this->assessor = $assessor;
    }

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
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
