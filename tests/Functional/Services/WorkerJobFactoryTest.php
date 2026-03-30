<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerJob;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerJobFactory;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerJobFactoryTest extends WebTestCase
{
    private WorkerComponentStateRepository $workerComponentStateRepository;
    private WorkerJobFactory $workerJobFactory;

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

        $workerJobFactory = self::getContainer()->get(WorkerJobFactory::class);
        \assert($workerJobFactory instanceof WorkerJobFactory);
        $this->workerJobFactory = $workerJobFactory;
    }

    /**
     * @param callable(JobInterface, WorkerComponentStateRepository): void $componentStatesCreator
     * @param callable(JobInterface): WorkerJob                            $expectedWorkerJobCreator
     */
    #[DataProvider('createForJobDataProvider')]
    public function testCreateForJob(
        callable $componentStatesCreator,
        callable $expectedWorkerJobCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $componentStatesCreator($job, $this->workerComponentStateRepository);

        $workerJob = $this->workerJobFactory->createForJob($job);

        self::assertEquals($expectedWorkerJobCreator($job), $workerJob);
    }

    /**
     * @return array<mixed>
     */
    public static function createForJobDataProvider(): array
    {
        return [
            'no component state entities' => [
                'componentStatesCreator' => function () {},
                'expectedWorkerJobCreator' => function () {
                    return new WorkerJob(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'application component state entity only' => [
                'componentStatesCreator' => function (JobInterface $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        )
                            ->setState('awaiting-job')
                    );
                },
                'expectedWorkerJobCreator' => function (JobInterface $job) {
                    return new WorkerJob(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        )
                            ->setState('awaiting-job'),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'execution component state entity only' => [
                'componentStatesCreator' => function (JobInterface $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        )
                            ->setState('awaiting')
                    );
                },
                'expectedWorkerJobCreator' => function (JobInterface $job) {
                    return new WorkerJob(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        )
                            ->setState('awaiting'),
                        new PendingWorkerComponentState(),
                    );
                },
            ],
            'all component states' => [
                'componentStatesCreator' => function (JobInterface $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        )
                            ->setState('executing')
                    );

                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::COMPILATION,
                        )
                            ->setState('complete')
                            ->setMetaState(new MetaState(true, true))
                    );

                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        )
                            ->setState('running')
                    );

                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EVENT_DELIVERY,
                        )
                            ->setState('running')
                    );
                },
                'expectedWorkerJobCreator' => function (JobInterface $job) {
                    return new WorkerJob(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        )
                            ->setState('executing'),
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::COMPILATION,
                        )
                            ->setState('complete')
                            ->setMetaState(new MetaState(true, true)),
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        )
                            ->setState('running'),
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EVENT_DELIVERY,
                        )
                            ->setState('running'),
                    );
                },
            ],
        ];
    }
}
