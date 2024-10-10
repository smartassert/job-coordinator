<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Entity\WorkerComponentState;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Tests\Application\AbstractApplicationTest;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class GetJobSuccessTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    /**
     * @param callable(Job): RemoteRequest[]                                       $remoteRequestsCreator
     * @param callable(Job, ResultsJobRepository): ?ResultsJob                     $resultsJobCreator
     * @param callable(Job, SerializedSuiteRepository): ?SerializedSuite           $serializedSuiteCreator
     * @param callable(Job, MachineRepository): ?Machine                           $machineCreator
     * @param callable(Job, WorkerComponentStateRepository): void                  $workerComponentStatesCreator
     * @param callable(Job, ?ResultsJob, ?SerializedSuite, ?Machine): array<mixed> $expectedSerializedJobCreator
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

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

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

        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);
        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $job = $jobRepository->find($jobId);
        self::assertInstanceOf(Job::class, $job);

        $resultsJob = $resultsJobCreator($job, $resultsJobRepository);
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

        self::assertEquals($expectedSerializedJobCreator($job, $resultsJob, $serializedSuite, $machine), $responseData);
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

        $resultsJobCreatorCreator = function (string $state, ?string $endState) {
            return function (Job $job, ResultsJobRepository $resultsJobRepository) use ($state, $endState) {
                \assert('' !== $state);
                \assert('' !== $endState);

                $resultsJob = new ResultsJob($job->id, md5((string) rand()), $state, $endState);
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
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'pending',
                            'request_states' => [
                                'results_job' => 'pending',
                                'serialized_suite' => 'pending',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::REQUESTING),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'requesting',
                                'serialized_suite' => 'pending',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                                'type' => 'results/create',
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
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::HALTED),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0))
                            ->setState(RequestState::HALTED),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_GET, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                6,
                                'unable to resolve host "sources.example.com"'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_GET, 1))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_GET, 2))
                            ->setState(RequestState::SUCCEEDED),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'halted',
                                'serialized_suite' => 'halted',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                                'type' => 'results/create',
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
                                'type' => 'serialized-suite/get',
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
                'resultsJobCreator' => $resultsJobCreatorCreator(md5((string) rand()), null),
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'succeeded',
                                'serialized_suite' => 'pending',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => [
                            'state' => $resultsJob->getState(),
                            'end_state' => null,
                        ],
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                'resultsJobCreator' => $resultsJobCreatorCreator(md5((string) rand()), md5((string) rand())),
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'succeeded',
                                'serialized_suite' => 'pending',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => [
                            'state' => $resultsJob->getState(),
                            'end_state' => $resultsJob->getEndState(),
                        ],
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                    Job $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) {
                    $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), 'prepared');
                    $serializedSuiteRepository->save($serializedSuite);

                    return $serializedSuite;
                },
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'pending',
                                'serialized_suite' => 'succeeded',
                                'machine' => 'pending',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => [
                            'state' => 'prepared',
                        ],
                        'machine' => null,
                        'worker_job' => [
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
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::MACHINE_CREATE, 0))
                            ->setState(RequestState::REQUESTING),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'pending',
                                'serialized_suite' => 'pending',
                                'machine' => 'requesting',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, md5((string) rand()), md5((string) rand()));
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (
                    Job $job,
                    ?ResultsJob $resultsJob,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'pending',
                                'serialized_suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker_job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                        ],
                        'worker_job' => [
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
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, md5((string) rand()), md5((string) rand()));
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedSerializedJobCreator' => function (
                    Job $job,
                    ?ResultsJob $resultsJob,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'preparing',
                            'request_states' => [
                                'results_job' => 'pending',
                                'serialized_suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker_job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                        ],
                        'worker_job' => [
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
                    Job $job,
                    SerializedSuiteRepository $serializedSuiteRepository
                ) {
                    $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), 'prepared');
                    $serializedSuiteRepository->save($serializedSuite);

                    return $serializedSuite;
                },
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, md5((string) rand()), md5((string) rand()));
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
                },
                'workerComponentStatesCreator' => function (Job $job, WorkerComponentStateRepository $repository) {
                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::APPLICATION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::COMPILATION))
                            ->setState('complete')
                            ->setIsEndState(true)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EXECUTION))
                            ->setState('running')
                            ->setIsEndState(false)
                    );

                    $repository->save(
                        (new WorkerComponentState($job->id, WorkerComponentName::EVENT_DELIVERY))
                            ->setState('running')
                            ->setIsEndState(false)
                    );
                },
                'expectedSerializedJobCreator' => function (
                    Job $job,
                    ?ResultsJob $resultsJob,
                    ?SerializedSuite $serializedSuite,
                    Machine $machine,
                ) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'succeeded',
                            'request_states' => [
                                'results_job' => 'succeeded',
                                'serialized_suite' => 'succeeded',
                                'machine' => 'succeeded',
                                'worker_job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'results_job' => [
                            'state' => 'awaiting-events',
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'state' => 'prepared',
                        ],
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                        ],
                        'worker_job' => [
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
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::NETWORK,
                                28,
                                'connection timed out'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::MACHINE_CREATE, 0))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                RemoteRequestFailureType::HTTP,
                                500,
                                'internal server error'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::MACHINE_START_JOB, 0))
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
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'created_at' => $job->getCreatedAt(),
                        'preparation' => [
                            'state' => 'failed',
                            'request_states' => [
                                'results_job' => 'failed',
                                'serialized_suite' => 'failed',
                                'machine' => 'failed',
                                'worker_job' => 'failed',
                            ],
                            'failures' => [
                                'results_job' => [
                                    'type' => 'http',
                                    'code' => 503,
                                    'message' => 'service unavailable',
                                ],
                                'serialized_suite' => [
                                    'type' => 'network',
                                    'code' => 28,
                                    'message' => 'connection timed out',
                                ],
                                'machine' => [
                                    'type' => 'http',
                                    'code' => 500,
                                    'message' => 'internal server error',
                                ],
                                'worker_job' => [
                                    'type' => 'network',
                                    'code' => 6,
                                    'message' => 'hostname lookup failed',
                                ],
                            ],
                        ],
                        'results_job' => null,
                        'serialized_suite' => null,
                        'machine' => null,
                        'worker_job' => [
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
                                'type' => 'machine/start-job',
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
                            [
                                'type' => 'results/create',
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
                        ],
                    ];
                },
            ],
        ];
    }
}
