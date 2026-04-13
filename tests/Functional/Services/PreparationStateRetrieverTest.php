<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\SerializedSuite;
use App\Entity\WorkerComponentState;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\PreparationStateRetriever;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PreparationStateRetrieverTest extends WebTestCase
{
    private PreparationStateRetriever $retriever;
    private RemoteRequestRepository $remoteRequestRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $retriever = self::getContainer()->get(PreparationStateRetriever::class);
        \assert($retriever instanceof PreparationStateRetriever);
        $this->retriever = $retriever;

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
     *   ResultsJobFactory,
     *   SerializedSuiteRepository,
     *   MachineRepository,
     *   WorkerComponentStateRepository,
     * ): void $entityCreator
     * @param callable(JobInterface, RemoteRequestRepository): void $remoteRequestsCreator
     * @param PreparationState[]                                    $expected
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

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $entityCreator(
            $job,
            $resultsJobFactory,
            $serializedSuiteRepository,
            $machineRepository,
            $workerComponentStateRepository
        );

        $remoteRequestsCreator($job, $this->remoteRequestRepository);

        self::assertEquals($expected, $this->retriever->getAll($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function getAllDataProvider(): array
    {
        $allEntitiesCreator = function (
            JobInterface $job,
            ResultsJobFactory $resultsJobFactory,
            SerializedSuiteRepository $serializedSuiteRepository,
            MachineRepository $machineRepository,
            WorkerComponentStateRepository $workerComponentStateRepository,
        ) {
            $resultsJobFactory->create(job: $job, state: 'awaiting-events');

            $serializedSuiteRepository->save(new SerializedSuite(
                $job->getId(),
                md5((string) rand()),
                'requested',
                new MetaState(false, false),
            ));

            $machineRepository->save(new Machine(
                $job->getId(),
                md5((string) rand()),
                md5((string) rand()),
                new MetaState(false, false),
            ));

            $workerComponentStateRepository->save(
                new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION)
                    ->setState('awaiting-job')
            );
        };

        return [
            'no entities, no remote requests' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function () {},
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single serialized-suite/create request with state "requesting"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteCreation(), 0)
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single machine/create request with state "requesting"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation(), 0)
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single machine/start-job request with state "requesting"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobCreation(), 0)
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                ],
            ],
            'no entities, single results/create request with state "requesting"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::REQUESTING)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single results/create request with state "halted"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::HALTED)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single results/create request with state "pending"' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::PENDING)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single results/create request of state "failed", no remote request failure' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::FAILED,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'no entities, single results/create request of state "failed", has remote request failure' => [
                'entityCreator' => function () {},
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            ))
                    );
                },
                'expected' => [
                    PreparationState::PENDING,
                    PreparationState::FAILED,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
            ],
            'has entities, no remote requests' => [
                'entityCreator' => $allEntitiesCreator,
                'remoteRequestsCreator' => function () {},
                'expected' => [
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                ],
            ],
            'has results job entity, has failed request for all components' => [
                'entityCreator' => $allEntitiesCreator,
                'remoteRequestsCreator' => function (
                    JobInterface $job,
                    RemoteRequestRepository $repository
                ) {
                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteCreation(), 0)
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation(), 0)
                            ->setState(RequestState::FAILED)
                    );

                    $repository->save(
                        new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                    );
                },
                'expected' => [
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                ],
            ],
        ];
    }
}
