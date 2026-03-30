<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Entity\WorkerComponentState;
use App\Entity\WorkerJobCreationFailure;
use App\Enum\WorkerComponentName;
use App\Enum\WorkerJobCreationStage;
use App\Model\JobComponent\WorkerJob;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\PendingWorkerComponentState;
use App\Model\RemoteRequestCollection;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerJobCreationFailureRepository as FailureRepository;
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
     * @param callable(JobInterface, WorkerComponentStateRepository): void         $componentStatesCreator
     * @param callable(JobInterface, FailureRepository): ?WorkerJobCreationFailure $workerJobCreationFailureCreator
     * @param callable(JobInterface, RemoteRequestRepository): void                $remoteRequestsCreator
     * @param callable(JobInterface): WorkerJob                                    $expectedWorkerJobCreator
     */
    #[DataProvider('createForJobDataProvider')]
    public function testCreateForJob(
        callable $componentStatesCreator,
        callable $workerJobCreationFailureCreator,
        callable $remoteRequestsCreator,
        callable $expectedWorkerJobCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerJobCreationFailureRepository = self::getContainer()->get(FailureRepository::class);
        \assert($workerJobCreationFailureRepository instanceof FailureRepository);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $componentStatesCreator($job, $this->workerComponentStateRepository);
        $workerJobCreationFailureCreator($job, $workerJobCreationFailureRepository);
        $remoteRequestsCreator($job, $remoteRequestRepository);

        $workerJob = $this->workerJobFactory->createForJob($job);

        self::assertEquals($expectedWorkerJobCreator($job), $workerJob);
    }

    /**
     * @return array<mixed>
     */
    public static function createForJobDataProvider(): array
    {
        return [
            'no component state entities, no remote requests, no creation failure' => [
                'componentStatesCreator' => function () {},
                'workerJobCreationFailureCreator' => function () {},
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {},
                'expectedWorkerJobCreator' => function () {
                    return new WorkerJob(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        null,
                        new RemoteRequestCollection([]),
                    );
                },
            ],
            'no component state entities, no remote requests, has creation failure' => [
                'componentStatesCreator' => function () {},
                'workerJobCreationFailureCreator' => function (JobInterface $job, FailureRepository $repository) {
                    $failure = new WorkerJobCreationFailure(
                        $job->getId(),
                        WorkerJobCreationStage::WORKER_JOB_CREATE,
                        new \RuntimeException('exception message', 123),
                    );

                    $repository->save($failure);
                },
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {},
                'expectedWorkerJobCreator' => function (JobInterface $job) {
                    return new WorkerJob(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new WorkerJobCreationFailure(
                            $job->getId(),
                            WorkerJobCreationStage::WORKER_JOB_CREATE,
                            new \RuntimeException('exception message', 123),
                        ),
                        new RemoteRequestCollection([]),
                    );
                },
            ],
            'no component state entities, no remote requests, has remote requests' => [
                'componentStatesCreator' => function () {},
                'workerJobCreationFailureCreator' => function () {},
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobCreation(), 0),
                    );

                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobRetrieval(), 0),
                    );
                },
                'expectedWorkerJobCreator' => function (JobInterface $job) {
                    return new WorkerJob(
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        new PendingWorkerComponentState(),
                        null,
                        new RemoteRequestCollection([
                            new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobCreation(), 0),
                            new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobRetrieval(), 0),
                        ]),
                    );
                },
            ],
            'application component state entity only, no remote requests, no creation failure' => [
                'componentStatesCreator' => function (JobInterface $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::APPLICATION,
                        )
                            ->setState('awaiting-job')
                    );
                },
                'workerJobCreationFailureCreator' => function () {},
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {},
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
                        null,
                        new RemoteRequestCollection([]),
                    );
                },
            ],
            'execution component state entity only, no remote requests, no creation failure' => [
                'componentStatesCreator' => function (JobInterface $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        new WorkerComponentState(
                            $job->getId(),
                            WorkerComponentName::EXECUTION,
                        )
                            ->setState('awaiting')
                    );
                },
                'workerJobCreationFailureCreator' => function () {},
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {},
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
                        null,
                        new RemoteRequestCollection([]),
                    );
                },
            ],
            'all component states, no remote requests, no creation failure' => [
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
                'workerJobCreationFailureCreator' => function () {},
                'remoteRequestsCreator' => function (JobInterface $job, RemoteRequestRepository $repository) {},
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
                        null,
                        new RemoteRequestCollection([]),
                    );
                },
            ],
        ];
    }
}
