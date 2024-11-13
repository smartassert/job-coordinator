<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerState;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerStateFactory;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerStateFactoryTest extends WebTestCase
{
    private WorkerComponentStateRepository $workerComponentStateRepository;
    private WorkerStateFactory $workerStateFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);
        foreach ($workerComponentStateRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->workerComponentStateRepository = $workerComponentStateRepository;

        $workerStateFactory = self::getContainer()->get(WorkerStateFactory::class);
        \assert($workerStateFactory instanceof WorkerStateFactory);
        $this->workerStateFactory = $workerStateFactory;
    }

    /**
     * @param callable(Job, WorkerComponentStateRepository): void $componentStatesCreator
     * @param callable(Job): WorkerState                          $expectedWorkerStateCreator
     */
    #[DataProvider('createForJobDataProvider')]
    public function testCreateForJob(
        callable $componentStatesCreator,
        callable $expectedWorkerStateCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $componentStatesCreator($job, $this->workerComponentStateRepository);

        $workerState = $this->workerStateFactory->createForJob($job);

        self::assertEquals($expectedWorkerStateCreator($job), $workerState);
    }

    /**
     * @return array<mixed>
     */
    public static function createForJobDataProvider(): array
    {
        return [
            'no component state entities' => [
                'componentStatesCreator' => function () {
                },
                'expectedWorkerStateCreator' => function () {
                    return new WorkerState(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'application component state entity only' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    \assert('' !== $job->getId());

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    \assert('' !== $job->getId());

                    return new WorkerState(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('awaiting-job')
                            ->setIsEndState(false),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'execution component state entity only' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    \assert('' !== $job->getId());

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('awaiting')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    \assert('' !== $job->getId());

                    return new WorkerState(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('awaiting')
                            ->setIsEndState(false),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'all component states' => [
                'componentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    \assert('' !== $job->getId());

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EVENT_DELIVERY,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    \assert('' !== $job->getId());

                    return new WorkerState(
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true),
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EVENT_DELIVERY,
                        ))
                            ->setState('running')
                            ->setIsEndState(false),
                    );
                },
            ],
        ];
    }
}
