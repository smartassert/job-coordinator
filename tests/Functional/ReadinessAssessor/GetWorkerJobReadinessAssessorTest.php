<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\MessageHandlingReadiness;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetWorkerJobReadinessAssessor;
use App\Repository\WorkerComponentStateRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetWorkerJobReadinessAssessorTest extends WebTestCase
{
    private GetWorkerJobReadinessAssessor $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(GetWorkerJobReadinessAssessor::class);
        \assert($assessor instanceof GetWorkerJobReadinessAssessor);

        $this->assessor = $assessor;
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
                    $applicationState->setMetaState(new MetaState(true, true, false));

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
                    $applicationState->setMetaState(new MetaState(false, false, true));

                    $workerComponentStateRepository->save($applicationState);
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
