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
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
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
     *   JobInterface,
     *   ResultsJobRepository,
     *   SerializedSuiteRepository,
     *   MachineRepository,
     *   WorkerComponentStateRepository
     * ): void $entityCreator
     * @param callable(JobInterface, RemoteRequestRepository): void $remoteRequestCreator
     * @param array<mixed>                                          $expected
     */
    #[DataProvider('createDataProvider')]
    public function testCreate(callable $entityCreator, callable $remoteRequestCreator, array $expected): void
    {
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
        $resultsJobCreateType = new RemoteRequestType(
            JobComponent::RESULTS_JOB,
            RemoteRequestAction::CREATE,
        );

        $serializedSuiteCreateType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::CREATE,
        );

        return [
            'pending' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function () {
                },
                'expected' => [
                    'state' => PreparationStateEnum::PENDING,
                    'failures' => [],
                    'request_states' => [
                        'results-job' => RequestState::PENDING,
                        'serialized-suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker-job' => RequestState::PENDING,
                    ],
                ],
            ],
            'succeeded' => [
                'entityCreator' => function (
                    JobInterface $job,
                    ResultsJobRepository $resultsJobRepository,
                    SerializedSuiteRepository $serializedSuiteRepository,
                    MachineRepository $machineRepository,
                    WorkerComponentStateRepository $workerComponentStateRepository
                ) {
                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), md5((string) rand()), md5((string) rand()), null)
                    );
                    $serializedSuiteRepository->save(
                        new SerializedSuite(
                            $job->getId(),
                            md5((string) rand()),
                            md5((string) rand()),
                            false,
                            false
                        )
                    );
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
                },
                'remoteRequestCreator' => function () {
                },
                'expected' => [
                    'state' => PreparationStateEnum::SUCCEEDED,
                    'failures' => [],
                    'request_states' => [
                        'results-job' => RequestState::SUCCEEDED,
                        'serialized-suite' => RequestState::SUCCEEDED,
                        'machine' => RequestState::SUCCEEDED,
                        'worker-job' => RequestState::SUCCEEDED,
                    ],
                ],
            ],
            'preparing' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $remoteRequestRepository
                ) use (
                    $resultsJobCreateType
                ) {
                    $remoteRequestRepository->save(
                        new RemoteRequest($job->getId(), $resultsJobCreateType, 0)
                    );
                },
                'expected' => [
                    'state' => PreparationStateEnum::PREPARING,
                    'failures' => [],
                    'request_states' => [
                        'results-job' => RequestState::REQUESTING,
                        'serialized-suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker-job' => RequestState::PENDING,
                    ],
                ],
            ],
            'failed, single component failure' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $remoteRequestRepository
                ) use (
                    $resultsJobCreateType
                ) {
                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->getId(), $resultsJobCreateType, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => [
                    'state' => PreparationStateEnum::FAILED,
                    'failures' => [
                        'results-job' => new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        ),
                    ],
                    'request_states' => [
                        'results-job' => RequestState::FAILED,
                        'serialized-suite' => RequestState::PENDING,
                        'machine' => RequestState::PENDING,
                        'worker-job' => RequestState::PENDING,
                    ],
                ],
            ],
            'failed, multiple component failures' => [
                'entityCreator' => function () {
                },
                'remoteRequestCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $remoteRequestRepository
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                ) {
                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->getId(), $resultsJobCreateType, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );

                    $remoteRequestRepository->save(
                        (new RemoteRequest($job->getId(), $serializedSuiteCreateType, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                28,
                                'connection timed out'
                            ))
                    );
                },
                'expected' => [
                    'state' => PreparationStateEnum::FAILED,
                    'failures' => [
                        'results-job' => new RemoteRequestFailure(
                            RemoteRequestFailureType::HTTP,
                            503,
                            'service unavailable'
                        ),
                        'serialized-suite' => new RemoteRequestFailure(
                            RemoteRequestFailureType::NETWORK,
                            28,
                            'connection timed out'
                        ),
                    ],
                    'request_states' => [
                        'results-job' => RequestState::FAILED,
                        'serialized-suite' => RequestState::FAILED,
                        'machine' => RequestState::PENDING,
                        'worker-job' => RequestState::PENDING,
                    ],
                ],
            ],
        ];
    }
}
