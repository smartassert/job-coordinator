<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Entity\WorkerComponentState;
use App\Enum\JobComponent;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\ComponentPreparation;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\ComponentPreparationFactory;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @param callable(
     *   JobInterface,
     *   ResultsJobRepository,
     *   SerializedSuiteRepository,
     *   MachineRepository,
     *   WorkerComponentStateRepository,
     * ): void    $entityCreator
     * @param callable(JobInterface, RemoteRequestRepository): void $remoteRequestsCreator
     * @param ComponentPreparation[]                                $expected
     */
    #[DataProvider('getAllDataProvider')]
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
            JobInterface $job,
            ResultsJobRepository $resultsJobRepository,
            SerializedSuiteRepository $serializedSuiteRepository,
            MachineRepository $machineRepository,
            WorkerComponentStateRepository $workerComponentStateRepository,
        ) {
            $resultsJobRepository->save(new ResultsJob(
                $job->getId(),
                'results job token',
                'awaiting-events',
                null
            ));

            $serializedSuiteRepository->save(new SerializedSuite(
                $job->getId(),
                md5((string) rand()),
                'requested',
                false,
                false
            ));

            $machineRepository->save(new Machine(
                $job->getId(),
                md5((string) rand()),
                md5((string) rand()),
                false,
                false,
            ));

            $workerComponentStateRepository->save(
                (new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION))
                    ->setState('awaiting-job')
                    ->setIsEndState(false)
            );
        };

        $resultsCreateType = new RemoteRequestType(
            JobComponent::RESULTS_JOB,
            RemoteRequestAction::CREATE,
        );

        $serializedSuiteCreateType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::CREATE,
        );

        $machineCreateType = new RemoteRequestType(
            JobComponent::MACHINE,
            RemoteRequestAction::CREATE,
        );

        $workerJobCreateType = new RemoteRequestType(
            JobComponent::WORKER_JOB,
            RemoteRequestAction::CREATE,
        );

        $expectedAllSuccess = [
            JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                JobComponent::RESULTS_JOB,
                PreparationState::SUCCEEDED
            ),
            JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                JobComponent::SERIALIZED_SUITE,
                PreparationState::SUCCEEDED
            ),
            JobComponent::MACHINE->value => new ComponentPreparation(
                JobComponent::MACHINE,
                PreparationState::SUCCEEDED
            ),
            JobComponent::WORKER_JOB->value => new ComponentPreparation(
                JobComponent::WORKER_JOB,
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
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single serialized-suite/create request with state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $serializedSuiteCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $serializedSuiteCreateType, 0))
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single machine/create request with state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use ($machineCreateType) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $machineCreateType, 0))
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single machine/start-job request with state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $workerJobCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $workerJobCreateType, 0))
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PREPARING
                    ),
                ],
            ],
            'no entities, single results/create request with state "requesting"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PREPARING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request with state "halted"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::HALTED)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PREPARING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request with state "pending"' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType,
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::PENDING)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PREPARING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request of state "failed", no remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::FAILED)
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::FAILED
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                ],
            ],
            'no entities, single results/create request of state "failed", has remote request failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::FAILED,
                        new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        )
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
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
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) use (
                    $resultsCreateType,
                    $serializedSuiteCreateType,
                    $machineCreateType,
                    $workerJobCreateType,
                ) {
                    $repository->save(
                        (new RemoteRequest($job->getId(), $resultsCreateType, 0))
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest($job->getId(), $serializedSuiteCreateType, 0))
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest($job->getId(), $machineCreateType, 0))
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        (new RemoteRequest($job->getId(), $workerJobCreateType, 0))
                            ->setState(RequestState::FAILED)
                    );
                },
                'expected' => $expectedAllSuccess,
            ],
        ];
    }
}
