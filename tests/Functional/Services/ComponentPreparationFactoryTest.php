<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Entity\WorkerComponentState;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\ComponentPreparation;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\ComponentPreparationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ComponentPreparationFactoryTest extends WebTestCase
{
    private ComponentPreparationFactory $componentPreparationFactory;
    private RemoteRequestRepository $remoteRequestRepository;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $componentPreparationFactory = self::getContainer()->get(ComponentPreparationFactory::class);
        \assert($componentPreparationFactory instanceof ComponentPreparationFactory);
        $this->componentPreparationFactory = $componentPreparationFactory;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }
        $this->remoteRequestRepository = $remoteRequestRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
    }

    /**
     * @dataProvider getForResultsJobDataProvider
     *
     * @param callable(Job, ResultsJobRepository): void    $entityCreator
     * @param callable(Job, RemoteRequestRepository): void $remoteRequestsCreator
     */
    public function testGetForResultsJob(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        ComponentPreparation $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $entityCreator($job, $resultsJobRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->componentPreparationFactory->getForResultsJob($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForResultsJobDataProvider(): array
    {
        return [
            'no entity, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::PENDING),
            ],
            'no entity, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "failed", no remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::FAILED),
            ],
            'no entity, single request of state "failed", has remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => new ComponentPreparation(
                    PreparationState::FAILED,
                    new RemoteRequestFailure(
                        RemoteRequestFailureType::HTTP,
                        503,
                        'service unavailable'
                    )
                ),
            ],
            'has entity, no remote requests' => [
                'entityCreator' => function (Job $job, ResultsJobRepository $repository) {
                    $repository->save(new ResultsJob(
                        $job->id,
                        'results job token',
                        'awaiting-events',
                        null
                    ));
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
            'has entity, has failed request' => [
                'entityCreator' => function (Job $job, ResultsJobRepository $repository) {
                    $repository->save(new ResultsJob(
                        $job->id,
                        'results job token',
                        'awaiting-events',
                        null
                    ));
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
        ];
    }

    /**
     * @dataProvider getForSerializedSuiteDataProvider
     *
     * @param callable(Job, SerializedSuiteRepository): void $entityCreator
     * @param callable(Job, RemoteRequestRepository): void   $remoteRequestsCreator
     */
    public function testGetForSerializedSuite(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        ComponentPreparation $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $entityCreator($job, $serializedSuiteRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->componentPreparationFactory->getForSerializedSuite($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForSerializedSuiteDataProvider(): array
    {
        return [
            'no entity, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::PENDING),
            ],
            'no entity, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "failed", no remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::FAILED),
            ],
            'no entity, single request of state "failed", has remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => new ComponentPreparation(
                    PreparationState::FAILED,
                    new RemoteRequestFailure(
                        RemoteRequestFailureType::HTTP,
                        503,
                        'service unavailable'
                    )
                ),
            ],
            'has entity, no remote requests' => [
                'entityCreator' => function (Job $job, SerializedSuiteRepository $repository) {
                    $repository->save(new SerializedSuite($job->id, md5((string) rand()), 'requested'));
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
            'has entity, has failed request' => [
                'entityCreator' => function (Job $job, SerializedSuiteRepository $repository) {
                    $repository->save(new SerializedSuite($job->id, md5((string) rand()), 'requested'));
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
        ];
    }

    /**
     * @dataProvider getForMachineDataProvider
     *
     * @param callable(Job, MachineRepository): void       $entityCreator
     * @param callable(Job, RemoteRequestRepository): void $remoteRequestsCreator
     */
    public function testGetForMachine(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        ComponentPreparation $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $entityCreator($job, $machineRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->componentPreparationFactory->getForMachine($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForMachineDataProvider(): array
    {
        return [
            'no entity, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::PENDING),
            ],
            'no entity, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entity, single request of state "failed", no remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::FAILED),
            ],
            'no entity, single request of state "failed", has remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => new ComponentPreparation(
                    PreparationState::FAILED,
                    new RemoteRequestFailure(
                        RemoteRequestFailureType::HTTP,
                        503,
                        'service unavailable'
                    )
                ),
            ],
            'has entity, no remote requests' => [
                'entityCreator' => function (Job $job, MachineRepository $repository) {
                    $repository->save(new Machine($job->id, md5((string) rand()), md5((string) rand())));
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
            'has entity, has failed request' => [
                'entityCreator' => function (Job $job, MachineRepository $repository) {
                    $repository->save(new Machine($job->id, md5((string) rand()), md5((string) rand())));
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
        ];
    }

    /**
     * @dataProvider getForWorkerJobDataProvider
     *
     * @param callable(Job, WorkerComponentStateRepository): void $entityCreator
     * @param callable(Job, RemoteRequestRepository): void        $remoteRequestsCreator
     */
    public function testGetForWorkerJob(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        ComponentPreparation $expected
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $entityCreator($job, $workerComponentStateRepository);
        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->componentPreparationFactory->getForWorkerJob($job));
    }

    /**
     * @return array<mixed>
     */
    public function getForWorkerJobDataProvider(): array
    {
        return [
            'no entities, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::PENDING),
            ],
            'no entities, single request of state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entities, single request of state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))->setState(RequestState::HALTED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entities, single request of state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::PREPARING),
            ],
            'no entities, single request of state "failed", no remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::FAILED),
            ],
            'no entities, single request of state "failed", has remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => new ComponentPreparation(
                    PreparationState::FAILED,
                    new RemoteRequestFailure(
                        RemoteRequestFailureType::HTTP,
                        503,
                        'service unavailable'
                    )
                ),
            ],
            'has single entity, no remote requests' => [
                'entityCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
            'has multiple entities, no remote requests' => [
                'entityCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('compiling')
                            ->setIsEndState(false)
                    );
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                            ->setState('pending')
                            ->setIsEndState(false)
                    );
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
            'has single entity, has failed request' => [
                'entityCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            1
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => new ComponentPreparation(PreparationState::SUCCEEDED),
            ],
        ];
    }
}
