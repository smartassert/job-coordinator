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
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\PreparationState as PreparationStateModel;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\PreparationStateFactory;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PreparationStateFactoryTest extends WebTestCase
{
    private PreparationStateFactory $preparationStateFactory;
    private RemoteRequestRepository $remoteRequestRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $preparationStateFactory = self::getContainer()->get(PreparationStateFactory::class);
        \assert($preparationStateFactory instanceof PreparationStateFactory);
        $this->preparationStateFactory = $preparationStateFactory;

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
     *   Job,
     *   ResultsJobRepository,
     *   SerializedSuiteRepository,
     *   MachineRepository,
     *   WorkerComponentStateRepository
     * ): void $entityCreator
     * @param callable(Job, RemoteRequestRepository): void $remoteRequestCreator
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(
        callable $entityCreator,
        callable $remoteRequestCreator,
        PreparationStateModel $expected
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

        $remoteRequestCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->preparationStateFactory->create($job));
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): array
    {
        return [
            'pending' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function () {
                },
                'expected' => new PreparationStateModel(
                    PreparationStateEnum::PENDING,
                    [],
                    [
                        'results_job' => RequestState::PENDING,
                        'serialized_suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker_job' => RequestState::PENDING,
                    ],
                ),
            ],
            'succeeded' => [
                'entityCreator' => function (
                    Job $job,
                    ResultsJobRepository $resultsJobRepository,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    MachineRepository $machineRepository,
                    WorkerComponentStateRepository $workerComponentStateRepository
                ) {
                    $resultsJobRepository->save(
                        new ResultsJob($job->id, md5((string) rand()), md5((string) rand()), null)
                    );
                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->id, md5((string) rand()), md5((string) rand()))
                    );
                    $machineRepository->save(new Machine($job->id, md5((string) rand()), md5((string) rand())));
                    $workerComponentStateRepository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('awaiting-job')
                            ->setIsEndState(false)
                    );
                },
                'remoteRequestCreator' => function () {
                },
                'expected' => new PreparationStateModel(
                    PreparationStateEnum::SUCCEEDED,
                    [],
                    [
                        'results_job' => RequestState::SUCCEEDED,
                        'serialized_suite' => RequestState::SUCCEEDED,
                        'machine' => RequestState::SUCCEEDED,
                        'worker_job' => RequestState::SUCCEEDED,
                    ],
                ),
            ],
            'preparing' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (Job $job, RemoteRequestRepository $remoteRequestRepository) {
                    $remoteRequestRepository->save(
                        new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0)
                    );
                },
                'expected' => new PreparationStateModel(
                    PreparationStateEnum::PREPARING,
                    [],
                    [
                        'results_job' => RequestState::REQUESTING,
                        'serialized_suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker_job' => RequestState::PENDING,
                    ],
                ),
            ],
            'failed, single component failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (Job $job, RemoteRequestRepository $remoteRequestRepository) {
                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => new PreparationStateModel(
                    PreparationStateEnum::FAILED,
                    [
                        'results_job' => new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        ),
                    ],
                    [
                        'results_job' => RequestState::FAILED,
                        'serialized_suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker_job' => RequestState::PENDING,
                    ],
                ),
            ],
            'failed, multiple component failures' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (Job $job, RemoteRequestRepository $remoteRequestRepository) {
                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );

                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                28,
                                'connection timed out'
                            ))
                    );
                },
                'expected' => new PreparationStateModel(
                    PreparationStateEnum::FAILED,
                    [
                        'results_job' => new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        ),
                        'serialized_suite' => new RemoteRequestFailure(
                            RemoteRequestFailureType::NETWORK,
                            28,
                            'connection timed out'
                        ),
                    ],
                    [
                        'results_job' => RequestState::FAILED,
                        'serialized_suite' => RequestState::FAILED,
                        'machine' => RequestState::PENDING,
                        'worker_job' => RequestState::PENDING,
                    ],
                ),
            ],
        ];
    }
}
