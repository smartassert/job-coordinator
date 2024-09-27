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
use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\ComponentPreparationFactory;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ComponentPreparationFactoryTest extends WebTestCase
{
    private ComponentPreparationFactory $componentPreparationFactory;
    private RemoteRequestRepository $remoteRequestRepository;

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

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
    }

    /**
     * @dataProvider getAllDataProvider
     *
     * @param callable(
     *   Job,
     *   ResultsJobRepository,
     *   SerializedSuiteRepository,
     *   MachineRepository,
     *   WorkerComponentStateRepository,
     * ): void    $entityCreator
     * @param callable(Job, RemoteRequestRepository): void $remoteRequestsCreator
     * @param ComponentPreparation[]                       $expected
     */
    public function testGetAll(
        callable $entityCreator,
        callable $remoteRequestsCreator,
        array $expected
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $entityCreator(
            $job,
            $resultsJobRepository,
            $serializedSuiteRepository,
            $machineRepository,
            $workerComponentStateRepository
        );

        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->componentPreparationFactory->getAll($job));
    }

    /**
     * @return array<mixed>
     */
    public static function getAllDataProvider(): array
    {
        $allEntitiesCreator = function (
            Job $job,
            ResultsJobRepository $resultsJobRepository,
            SerializedSuiteRepository $serializedSuiteRepository,
            MachineRepository $machineRepository,
            WorkerComponentStateRepository $workerComponentStateRepository,
        ) {
            $resultsJobRepository->save(new ResultsJob(
                $job->id,
                'results job token',
                'awaiting-events',
                null
            ));

            $serializedSuiteRepository->save(new SerializedSuite($job->id, md5((string) rand()), 'requested'));

            $machineRepository->save(new Machine($job->id, md5((string) rand()), md5((string) rand())));

            $workerComponentStateRepository->save(
                (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                    ->setState('awaiting-job')
                    ->setIsEndState(false)
            );
        };

        $resultsComponent = new JobComponent(JobComponentName::RESULTS_JOB, RemoteRequestType::RESULTS_CREATE);
        $serializedSuiteComponent = new JobComponent(
            JobComponentName::SERIALIZED_SUITE,
            RemoteRequestType::SERIALIZED_SUITE_CREATE
        );
        $machineComponent = new JobComponent(JobComponentName::MACHINE, RemoteRequestType::MACHINE_CREATE);
        $workerComponent = new JobComponent(JobComponentName::WORKER_JOB, RemoteRequestType::MACHINE_START_JOB);

        $expectedAllSuccess = [
            JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                $resultsComponent,
                PreparationState::SUCCEEDED
            ),
            JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                $serializedSuiteComponent,
                PreparationState::SUCCEEDED
            ),
            JobComponentName::MACHINE->value => new ComponentPreparation(
                $machineComponent,
                PreparationState::SUCCEEDED
            ),
            JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                $workerComponent,
                PreparationState::SUCCEEDED
            ),
        ];

        return [
            'no entities, no remote requests' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function () {
                },
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single serialized-suite/create request with state "requesting"' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single machine/create request with state "requesting"' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single machine/start-job request with state "requesting"' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PREPARING
                    ),
                ],
            ],
            'no entities, single results/create request with state "requesting"' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request with state "halted"' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request with state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::PENDING)
                    );
                },
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request of state "failed", no remote request failure' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::FAILED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request of state "failed", has remote request failure' => [
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
                'expected' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::FAILED,
                        new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        )
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'has entities, no remote requests' => [
                'entityCreator' => $allEntitiesCreator,
                'remoteRequestsCreator' => function () {
                },
                'expected' => $expectedAllSuccess,
            ],
            'has results job entity, has failed request for all components' => [
                'entityCreator' => $allEntitiesCreator,
                'remoteRequestsCreator' => function (Job $job, RemoteRequestRepository $repository) {
                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::RESULTS_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::SERIALIZED_SUITE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_CREATE,
                            0
                        ))->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest(
                            $job->id,
                            RemoteRequestType::MACHINE_START_JOB,
                            0
                        ))->setState(RequestState::FAILED)
                    );
                },
                'expected' => $expectedAllSuccess,
            ],
        ];
    }
}
