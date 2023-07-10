<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerState;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerStateFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WorkerStateFactoryTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private WorkerComponentStateRepository $workerComponentStateRepository;
    private WorkerStateFactory $workerStateFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

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
     * @dataProvider createForJobDataProvider
     *
     * @param callable(Job, WorkerComponentStateRepository): void $componentStatesCreator
     * @param callable(Job): WorkerState                          $expectedWorkerStateCreator
     */
    public function testCreateForJob(
        callable $componentStatesCreator,
        callable $expectedWorkerStateCreator,
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $componentStatesCreator($job, $this->workerComponentStateRepository);

        $workerState = $this->workerStateFactory->createForJob($job);

        self::assertEquals($expectedWorkerStateCreator($job), $workerState);
    }

    /**
     * @return array<mixed>
     */
    public function createForJobDataProvider(): array
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
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerState(
                        (new WorkerComponentState(
                            $job->id,
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
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('awaiting')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerState(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        (new WorkerComponentState(
                            $job->id,
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
                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EVENT_DELIVERY,
                        ))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedWorkerStateCreator' => function (Job $job) {
                    return new WorkerState(
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::APPLICATION,
                        ))
                            ->setState('executing')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::COMPILATION,
                        ))
                            ->setState('complete')
                            ->setIsEndState(true),
                        (new WorkerComponentState(
                            $job->id,
                            WorkerComponentName::EXECUTION,
                        ))
                            ->setState('running')
                            ->setIsEndState(false),
                        (new WorkerComponentState(
                            $job->id,
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
