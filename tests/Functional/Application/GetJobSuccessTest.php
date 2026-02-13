<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Machine;
use App\Entity\MachineActionFailure;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Entity\WorkerComponentState;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\JobStore;
use App\Tests\Application\AbstractApplicationTest;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class GetJobSuccessTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    /**
     * @param callable(JobInterface): RemoteRequest[]                             $remoteRequestsCreator
     * @param callable(JobInterface, ResultsJobRepository): ?ResultsJob           $resultsJobCreator
     * @param callable(JobInterface, SerializedSuiteRepository): ?SerializedSuite $serializedSuiteCreator
     * @param callable(JobInterface, MachineRepository): ?Machine                 $machineCreator
     * @param callable(JobInterface, WorkerComponentStateRepository): void        $workerComponentStatesCreator
     * @param callable(JobInterface, ?SerializedSuite, ?Machine): array<mixed>    $expectedSerializedJobCreator
     */
    #[DataProvider('getDataProvider')]
    public function testGetSuccess(
        callable $remoteRequestsCreator,
        callable $resultsJobCreator,
        callable $serializedSuiteCreator,
        callable $machineCreator,
        callable $workerComponentStatesCreator,
        callable $expectedSerializedJobCreator
    ): void {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $suiteId = (string) new Ulid();
        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);
        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $job = $jobStore->retrieve($jobId);
        self::assertNotNull($job);

        $resultsJobCreator($job, $resultsJobRepository);
        $serializedSuite = $serializedSuiteCreator($job, $serializedSuiteRepository);
        $machine = $machineCreator($job, $machineRepository);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);
        $workerComponentStatesCreator($job, $workerComponentStateRepository);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $remoteRequest) {
            $remoteRequestRepository->remove($remoteRequest);
        }

        foreach ($remoteRequestFailureRepository->findAll() as $remoteRequestFailure) {
            $entityManager->remove($remoteRequestFailure);
        }
        $entityManager->flush();

        $remoteRequests = $remoteRequestsCreator($job);
        foreach ($remoteRequests as $remoteRequest) {
            $remoteRequestRepository->save($remoteRequest);
        }

        $getResponse = self::$staticApplicationClient->makeGetJobRequest($apiToken, $jobId);
        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('application/json', $getResponse->getHeaderLine('content-type'));

        $responseData = json_decode($getResponse->getBody()->getContents(), true);

        self::assertEquals($expectedSerializedJobCreator($job, $serializedSuite, $machine), $responseData);
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        $nullCreator = function () {
            return null;
        };

        $emptyRemoteRequestsCreator = function () {
            return [];
        };

        $resultsJobCreatorCreator = function (
            string $state,
            ?string $endState = null,
            ?MetaState $metaState = null,
        ) {
            $metaState = $metaState ?? new MetaState(false, false);

            return function (
                JobInterface $job,
                ResultsJobRepository $resultsJobRepository
            ) use (
                $state,
                $endState,
                $metaState
            ) {
                \assert('' !== $state);
                \assert('' !== $endState);

                $resultsJob = new ResultsJob(
                    $job->getId(),
                    md5((string) rand()),
                    $state,
                    $endState,
                    $metaState,
                );
                $resultsJobRepository->save($resultsJob);

                return $resultsJob;
            };
        };

        return [
            'no remote requests, no results job, no serialized suite, no machine, no worker state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'pending',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'results/create: requesting' => [
                'remoteRequestsCreator' => function (JobInterface $job) {
                    return [
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::REQUESTING),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'requesting',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [
                            [
                                'type' => 'results-job/create',
                                'attempts' => [
                                    [
                                        'state' => 'requesting',
                                    ],
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'results/create halted, serialized-suite/create halted, serialized-suite/g failure and success' => [
                'remoteRequestsCreator' => function (
                    JobInterface $job
                ) {
                    return [
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::HALTED),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteCreation(), 0)
                            ->setState(RequestState::HALTED),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteRetrieval(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                6,
                                'unable to resolve host "sources.example.com"'
                            )),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteRetrieval(), 1)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            )),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteRetrieval(), 2)
                            ->setState(RequestState::SUCCEEDED),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'halted',
                                'serialized-suite' => 'halted',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [
                            [
                                'type' => 'results-job/create',
                                'attempts' => [
                                    [
                                        'state' => 'halted',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'serialized-suite/create',
                                'attempts' => [
                                    [
                                        'state' => 'halted',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'serialized-suite/retrieve',
                                'attempts' => [
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'network',
                                            'code' => 6,
                                            'message' => 'unable to resolve host "sources.example.com"',
                                        ],
                                    ],
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'http',
                                            'code' => 503,
                                            'message' => 'service unavailable',
                                        ],
                                    ],
                                    [
                                        'state' => 'succeeded',
                                    ],
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'has results state, no results end state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $resultsJobCreatorCreator('results-state-1', null),
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => [
                            'state' => 'results-state-1',
                            'end_state' => null,
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                        ],
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has results state, has results end state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $resultsJobCreatorCreator('results-state-2', 'results-end-state-2'),
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => [
                            'state' => 'results-state-2',
                            'end_state' => 'results-end-state-2',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                        ],
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has serialized suite' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        md5((string) rand()),
                        'prepared',
                        true,
                        true,
                        new MetaState(true, true)
                    );
                    $serializedSuiteRepository->save($serializedSuite);

                    return $serializedSuite;
                },
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'succeeded',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => [
                            'state' => 'prepared',
                            'is_prepared' => true,
                            'has_end_state' => true,
                        ],
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'requesting machine' => [
                'remoteRequestsCreator' => function (JobInterface $job) {
                    return [
                        new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation(), 0)
                            ->setState(RequestState::REQUESTING),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'requesting',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [
                            [
                                'type' => 'machine/create',
                                'attempts' => [
                                    [
                                        'state' => 'requesting',
                                    ],
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'has machine, no worker state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine(
                        $job->getId(),
                        md5((string) rand()),
                        md5((string) rand()),
                        false,
                        false,
                    );
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (
                    JobInterface $job,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'has_failed_state' => false,
                            'has_end_state' => false,
                        ],
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has machine, has worker state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine(
                        $job->getId(),
                        md5((string) rand()),
                        md5((string) rand()),
                        false,
                        false,
                    );
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $repository,
                ) {
                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedSerializedJobCreator' => function (
                    JobInterface $job,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'has_failed_state' => false,
                            'has_end_state' => false,
                        ],
                        'worker-job' => [
                            'state' => 'running',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'complete',
                                    'is_end_state' => true,
                                ],
                                'execution' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has machine, has action failure' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine(
                        $job->getId(),
                        md5((string) rand()),
                        md5((string) rand()),
                        false,
                        false,
                    );
                    $machine = $machine->setIp(md5((string) rand()));
                    $machine->setActionFailure(new MachineActionFailure(
                        $job->getId(),
                        'find',
                        'vendor_authentication_failure'
                    ));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $repository
                ) {
                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedSerializedJobCreator' => function (
                    JobInterface $job,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => [
                                'action' => 'find',
                                'type' => 'vendor_authentication_failure',
                                'context' => null,
                            ],
                            'has_failed_state' => false,
                            'has_end_state' => false,
                        ],
                        'worker-job' => [
                            'state' => 'running',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'complete',
                                    'is_end_state' => true,
                                ],
                                'execution' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'prepared' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $resultsJobCreatorCreator('awaiting-events', null),
                'serializedSuiteCreator' => function (
                    JobInterface $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) {
                    $serializedSuite = new SerializedSuite(
                        $job->getId(),
                        md5((string) rand()),
                        'prepared',
                        true,
                        true,
                        new MetaState(true, true),
                    );
                    $serializedSuiteRepository->save($serializedSuite);

                    return $serializedSuite;
                },
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine(
                        $job->getId(),
                        md5((string) rand()),
                        md5((string) rand()),
                        false,
                        false,
                    );
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => function (
                    JobInterface $job,
                    WorkerComponentStateRepository $repository,
                ) {
                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::APPLICATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EXECUTION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedSerializedJobCreator' => function (
                    JobInterface $job,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'succeeded',
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'succeeded',
                                'machine' => 'succeeded',
                                'worker-job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => [
                            'state' => 'awaiting-events',
                            'end_state' => null,
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                        ],
                        'serialized-suite' => [
                            'state' => 'prepared',
                            'is_prepared' => true,
                            'has_end_state' => true,
                        ],
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'has_failed_state' => false,
                            'has_end_state' => false,
                        ],
                        'worker-job' => [
                            'state' => 'running',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'complete',
                                    'is_end_state' => true,
                                ],
                                'execution' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'running',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'all preparation failed' => [
                'remoteRequestsCreator' => function (
                    JobInterface $job
                ) {
                    return [
                        new RemoteRequest($job->getId(), RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            )),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForSerializedSuiteCreation(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                28,
                                'connection timed out'
                            )),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForMachineCreation(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                500,
                                'internal server error'
                            )),
                        new RemoteRequest($job->getId(), RemoteRequestType::createForWorkerJobCreation(), 0)
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                6,
                                'hostname lookup failed'
                            )),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'failed',
                            'request_states' => [
                                'results-job' => 'failed',
                                'serialized-suite' => 'failed',
                                'machine' => 'failed',
                                'worker-job' => 'failed',
                            ],
                            'failures' => [
                                'results-job' => [
                                    'type' => 'http',
                                    'code' => 503,
                                    'message' => 'service unavailable',
                                ],
                                'serialized-suite' => [
                                    'type' => 'network',
                                    'code' => 28,
                                    'message' => 'connection timed out',
                                ],
                                'machine' => [
                                    'type' => 'http',
                                    'code' => 500,
                                    'message' => 'internal server error',
                                ],
                                'worker-job' => [
                                    'type' => 'network',
                                    'code' => 6,
                                    'message' => 'hostname lookup failed',
                                ],
                            ],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => null,
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [
                            [
                                'type' => 'machine/create',
                                'attempts' => [
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'http',
                                            'code' => 500,
                                            'message' => 'internal server error',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'results-job/create',
                                'attempts' => [
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'http',
                                            'code' => 503,
                                            'message' => 'service unavailable',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'serialized-suite/create',
                                'attempts' => [
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'network',
                                            'code' => 28,
                                            'message' => 'connection timed out',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'worker-job/create',
                                'attempts' => [
                                    [
                                        'state' => 'failed',
                                        'failure' => [
                                            'type' => 'network',
                                            'code' => 6,
                                            'message' => 'hostname lookup failed',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'has machine with failed state' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine(
                        $job->getId(),
                        md5((string) rand()),
                        md5((string) rand()),
                        true,
                        true,
                    );
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (
                    JobInterface $job,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'has_failed_state' => true,
                            'has_end_state' => true,
                        ],
                        'worker-job' => [
                            'state' => 'pending',
                            'is_end_state' => false,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'is_end_state' => false,
                                ],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
        ];
    }
}
