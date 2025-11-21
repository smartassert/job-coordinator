<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\MessageHandlingReadiness;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\GetWorkerJobReadinessHandler;
use App\Repository\WorkerComponentStateRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetWorkerJobReadinessAssessorTest extends WebTestCase
{
    private GetWorkerJobReadinessHandler $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(GetWorkerJobReadinessHandler::class);
        \assert($assessor instanceof GetWorkerJobReadinessHandler);

        $this->assessor = $assessor;
    }

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
    }

    /**
     * @param callable(JobInterface, WorkerComponentStateRepository): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $setup($job, $workerComponentStateRepository);

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'application state has end state' => [
                'setup' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $workerComponentStateRepository
                ): void {
                    $applicationState = new WorkerComponentState(
                        $job->getId(),
                        WorkerComponentName::APPLICATION,
                    );

                    $applicationState->setState('state');
                    $applicationState->setIsEndState(true);

                    $workerComponentStateRepository->save($applicationState);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'application state does not exist' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::NOW,
            ],
            'application state is not end state' => [
                'setup' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $workerComponentStateRepository
                ): void {
                    $applicationState = new WorkerComponentState(
                        $job->getId(),
                        WorkerComponentName::APPLICATION,
                    );

                    $applicationState->setState('state');
                    $applicationState->setIsEndState(false);

                    $workerComponentStateRepository->save($applicationState);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
