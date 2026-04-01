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
use App\Entity\WorkerJobCreationFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RequestState;
use App\Enum\WorkerComponentName;
use App\Enum\WorkerJobCreationStage;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerJobCreationFailureRepository;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Model\StagingConfiguration;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\Stager;
use PHPUnit\Framework\Attributes\DataProvider;

class GetJobSuccessTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllResultsJobs();
        $entityRemover->removeAllRemoteRequests();
        $entityRemover->removeAllRemoteRequestFailures();
    }

    /**
     * @param callable(JobInterface, ?SerializedSuite, ?Machine): array<mixed> $expectedSerializedJobCreator
     */
    #[DataProvider('getDataProvider')]
    #[DataProvider('workerJobCreationFailedDataProvider')]
    #[DataProvider('workerJobComponentDataProvider')]
    public function testGetSuccess(
        StagingConfiguration $stagingConfiguration,
        callable $expectedSerializedJobCreator
    ): void {
        $stager = self::getContainer()->get(Stager::class);
        \assert($stager instanceof Stager);

        $stagingOutput = $stager->stage(self::$staticApplicationClient, $stagingConfiguration);

        $getResponse = self::$staticApplicationClient->makeGetJobRequest(
            $stagingOutput->getApiToken(),
            $stagingOutput->getJob()->getId(),
        );
        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('application/json', $getResponse->getHeaderLine('content-type'));

        $responseData = json_decode($getResponse->getBody()->getContents(), true);

        self::assertEquals(
            $expectedSerializedJobCreator(
                $stagingOutput->getJob(),
                $stagingOutput->getSerializedSuite(),
                $stagingOutput->getMachine(),
            ),
            $responseData
        );
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'no remote requests, no results job, no serialized suite, no machine, no worker state' => [
                'stagingConfiguration' => new StagingConfiguration(),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'pending',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'results/create: requesting' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withRemoteRequestsCreator(function (string $jobId, RemoteRequestRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0)
                                ->setState(RequestState::REQUESTING)
                        );
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'requesting',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
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
                'stagingConfiguration' => new StagingConfiguration()
                    ->withRemoteRequestsCreator(function (string $jobId, RemoteRequestRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0)
                                ->setState(RequestState::HALTED)
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0)
                                ->setState(RequestState::HALTED)
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::NETWORK,
                                    6,
                                    'unable to resolve host "sources.example.com"'
                                ))
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 1)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::HTTP,
                                    503,
                                    'service unavailable'
                                ))
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 2)
                                ->setState(RequestState::SUCCEEDED)
                        );
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'halted',
                                'serialized-suite' => 'halted',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
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
                'stagingConfiguration' => new StagingConfiguration()
                    ->withResultsJobCreator(function (string $jobId, ResultsJobRepository $repository) {
                        \assert('' !== $jobId);

                        $resultsJob = new ResultsJob(
                            $jobId,
                            md5((string) rand()),
                            'results-state-1',
                            null,
                            new MetaState(false, false)
                        );

                        $repository->save($resultsJob);

                        return $resultsJob;
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => [
                                'state' => 'results-state-1',
                                'end_state' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has results state, has results end state' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withResultsJobCreator(function (string $jobId, ResultsJobRepository $repository) {
                        \assert('' !== $jobId);

                        $resultsJob = new ResultsJob(
                            $jobId,
                            md5((string) rand()),
                            'results-state-2',
                            'results-end-state-2',
                            new MetaState(false, false)
                        );

                        $repository->save($resultsJob);

                        return $resultsJob;
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'pending',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => [
                                'state' => 'results-state-2',
                                'end_state' => 'results-end-state-2',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has serialized suite' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withSerializedSuiteCreator(function (string $jobId, SerializedSuiteRepository $repository) {
                        \assert('' !== $jobId);

                        $serializedSuite = new SerializedSuite(
                            $jobId,
                            md5((string) rand()),
                            'prepared',
                            new MetaState(true, true)
                        );
                        $repository->save($serializedSuite);

                        return $serializedSuite;
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'succeeded',
                                'machine' => 'pending',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => [
                                'state' => 'prepared',
                                'is_prepared' => true,
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                            ],
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'requesting machine' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withRemoteRequestsCreator(function (string $jobId, RemoteRequestRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0)
                                ->setState(RequestState::REQUESTING)
                        );
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'requesting',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
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
                'stagingConfiguration' => new StagingConfiguration()
                    ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                        \assert('' !== $jobId);

                        $machine = new Machine(
                            $jobId,
                            md5((string) rand()),
                            md5((string) rand()),
                            new MetaState(false, false),
                        );
                        $machine = $machine->setIp(md5((string) rand()));

                        $repository->save($machine);

                        return $machine;
                    }),
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
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => $machine->getStateCategory(),
                                'ip_address' => $machine->getIp(),
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has machine, has action failure' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                        \assert('' !== $jobId);

                        $machine = new Machine(
                            $jobId,
                            md5((string) rand()),
                            md5((string) rand()),
                            new MetaState(false, false),
                        );
                        $machine = $machine->setIp(md5((string) rand()));
                        $machine->setActionFailure(new MachineActionFailure(
                            $jobId,
                            'find',
                            'vendor_authentication_failure'
                        ));

                        $repository->save($machine);

                        return $machine;
                    }),
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
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
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
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'prepared' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withResultsJobCreator(function (string $jobId, ResultsJobRepository $repository) {
                        \assert('' !== $jobId);

                        $resultsJob = new ResultsJob(
                            $jobId,
                            md5((string) rand()),
                            'awaiting-events',
                            null,
                            new MetaState(false, false)
                        );

                        $repository->save($resultsJob);

                        return $resultsJob;
                    })
                    ->withSerializedSuiteCreator(function (string $jobId, SerializedSuiteRepository $repository) {
                        \assert('' !== $jobId);

                        $serializedSuite = new SerializedSuite(
                            $jobId,
                            md5((string) rand()),
                            'prepared',
                            new MetaState(true, true),
                        );
                        $repository->save($serializedSuite);

                        return $serializedSuite;
                    })
                    ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                        \assert('' !== $jobId);

                        $machine = new Machine(
                            $jobId,
                            md5((string) rand()),
                            md5((string) rand()),
                            new MetaState(false, false),
                        );
                        $machine = $machine->setIp(md5((string) rand()));

                        $repository->save($machine);

                        return $machine;
                    })
                    ->withWorkerComponentStatesCreator(
                        function (string $jobId, WorkerComponentStateRepository $repository) {
                            \assert('' !== $jobId);

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::APPLICATION)
                                    ->setState('running')
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::COMPILATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EXECUTION)
                                    ->setState('running')
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EVENT_DELIVERY)
                                    ->setState('running')
                            );
                        }
                    ),
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
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'succeeded',
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => true,
                            ],
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'succeeded',
                                'machine' => 'succeeded',
                                'worker-job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
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
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                            ],
                            'machine' => [
                                'state_category' => $machine->getStateCategory(),
                                'ip_address' => $machine->getIp(),
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'running',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'complete',
                                        'meta_state' => [
                                            'ended' => true,
                                            'succeeded' => true,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'running',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'running',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'succeeded',
                                    'request_state' => 'succeeded',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'all preparation failed' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withRemoteRequestsCreator(function (string $jobId, RemoteRequestRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::HTTP,
                                    503,
                                    'service unavailable'
                                ))
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::NETWORK,
                                    28,
                                    'connection timed out'
                                ))
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::HTTP,
                                    500,
                                    'internal server error'
                                ))
                        );

                        $repository->save(
                            new RemoteRequest($jobId, RemoteRequestType::createForWorkerJobCreation(), 0)
                                ->setState(RequestState::FAILED)
                                ->setFailure(new RemoteRequestFailure(
                                    RemoteRequestFailureType::NETWORK,
                                    6,
                                    'hostname lookup failed'
                                ))
                        );
                    }),
                'expectedSerializedJobCreator' => function (JobInterface $job) {
                    return [
                        'id' => $job->getId(),
                        'suite_id' => $job->getSuiteId(),
                        'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                        'created_at' => $job->toArray()['created_at'],
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'failed',
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => false,
                            ],
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
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => null,
                                'ip_address' => null,
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'failed',
                                    'request_state' => 'failed',
                                    'failure' => [
                                        'type' => 'network',
                                        'code' => 6,
                                        'message' => 'hostname lookup failed',
                                    ],
                                ],
                                'requests' => [
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
                'stagingConfiguration' => new StagingConfiguration()
                    ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                        \assert('' !== $jobId);

                        $machine = new Machine(
                            $jobId,
                            md5((string) rand()),
                            md5((string) rand()),
                            new MetaState(true, false),
                        );
                        $machine = $machine->setIp(md5((string) rand()));

                        $repository->save($machine);

                        return $machine;
                    }),
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
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'preparation' => [
                            'state' => 'preparing',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'request_states' => [
                                'results-job' => 'pending',
                                'serialized-suite' => 'pending',
                                'machine' => 'succeeded',
                                'worker-job' => 'pending',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => null,
                            'serialized-suite' => null,
                            'machine' => [
                                'state_category' => $machine->getStateCategory(),
                                'ip_address' => $machine->getIp(),
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => false,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'pending',
                                'meta_state' => [
                                    'ended' => false,
                                    'succeeded' => false,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'pending',
                                        'meta_state' => [
                                            'ended' => false,
                                            'succeeded' => false,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'pending',
                                    'request_state' => 'pending',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'succeeded' => [
                'stagingConfiguration' => new StagingConfiguration()
                    ->withResultsJobCreator(function (string $jobId, ResultsJobRepository $repository) {
                        \assert('' !== $jobId);

                        $resultsJob = new ResultsJob(
                            $jobId,
                            md5((string) rand()),
                            'ended',
                            'complete',
                            new MetaState(true, true)
                        );

                        $repository->save($resultsJob);

                        return $resultsJob;
                    })
                    ->withSerializedSuiteCreator(function (string $jobId, SerializedSuiteRepository $repository) {
                        \assert('' !== $jobId);

                        $serializedSuite = new SerializedSuite(
                            $jobId,
                            md5((string) rand()),
                            'prepared',
                            new MetaState(true, true),
                        );
                        $repository->save($serializedSuite);

                        return $serializedSuite;
                    })
                    ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                        \assert('' !== $jobId);

                        $machine = new Machine(
                            $jobId,
                            'complete',
                            'end',
                            new MetaState(true, true),
                        );
                        $machine = $machine->setIp(md5((string) rand()));

                        $repository->save($machine);

                        return $machine;
                    })
                    ->withWorkerComponentStatesCreator(
                        function (string $jobId, WorkerComponentStateRepository $repository) {
                            \assert('' !== $jobId);

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::APPLICATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::COMPILATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EXECUTION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EVENT_DELIVERY)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );
                        }
                    ),
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
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'preparation' => [
                            'state' => 'succeeded',
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => true,
                            ],
                            'request_states' => [
                                'results-job' => 'succeeded',
                                'serialized-suite' => 'succeeded',
                                'machine' => 'succeeded',
                                'worker-job' => 'succeeded',
                            ],
                            'failures' => [],
                        ],
                        'components' => [
                            'results-job' => [
                                'state' => 'ended',
                                'end_state' => 'complete',
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                            ],
                            'serialized-suite' => [
                                'state' => 'prepared',
                                'is_prepared' => true,
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                            ],
                            'machine' => [
                                'state_category' => 'end',
                                'ip_address' => $machine->getIp(),
                                'action_failure' => null,
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                            ],
                            'worker-job' => [
                                'state' => 'complete',
                                'meta_state' => [
                                    'ended' => true,
                                    'succeeded' => true,
                                ],
                                'components' => [
                                    'compilation' => [
                                        'state' => 'complete',
                                        'meta_state' => [
                                            'ended' => true,
                                            'succeeded' => true,
                                        ],
                                    ],
                                    'execution' => [
                                        'state' => 'complete',
                                        'meta_state' => [
                                            'ended' => true,
                                            'succeeded' => true,
                                        ],
                                    ],
                                    'event_delivery' => [
                                        'state' => 'complete',
                                        'meta_state' => [
                                            'ended' => true,
                                            'succeeded' => true,
                                        ],
                                    ],
                                ],
                                'preparation' => [
                                    'state' => 'succeeded',
                                    'request_state' => 'succeeded',
                                ],
                                'requests' => [],
                            ],
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function workerJobComponentDataProvider(): array
    {
        $stagingConfiguration = new StagingConfiguration()
            ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                \assert('' !== $jobId);

                $machine = new Machine(
                    $jobId,
                    md5((string) rand()),
                    md5((string) rand()),
                    new MetaState(false, false),
                );
                $machine = $machine->setIp(md5((string) rand()));

                $repository->save($machine);

                return $machine;
            })
        ;

        $expectedSerializedJobCreatorCreator = function (array $workerComponentsData) {
            return function (
                JobInterface $job,
                ?SerializedSuite $serializedSuite,
                Machine $machine
            ) use ($workerComponentsData) {
                return [
                    'id' => $job->getId(),
                    'suite_id' => $job->getSuiteId(),
                    'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                    'created_at' => $job->toArray()['created_at'],
                    'meta_state' => [
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'preparation' => [
                        'state' => 'preparing',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'request_states' => [
                            'results-job' => 'pending',
                            'serialized-suite' => 'pending',
                            'machine' => 'succeeded',
                            'worker-job' => 'succeeded',
                        ],
                        'failures' => [],
                    ],
                    'components' => [
                        'results-job' => null,
                        'serialized-suite' => null,
                        'machine' => [
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                        ],
                        'worker-job' => [
                            'state' => 'running',
                            'meta_state' => [
                                'ended' => false,
                                'succeeded' => false,
                            ],
                            'components' => $workerComponentsData,
                            'preparation' => [
                                'state' => 'succeeded',
                                'request_state' => 'succeeded',
                            ],
                            'requests' => [],
                        ],
                    ],
                    'service_requests' => [],
                ];
            };
        };

        return [
            'worker-job: application running, compilation complete' => [
                'stagingConfiguration' => $stagingConfiguration
                    ->withWorkerComponentStatesCreator(
                        function (string $jobId, WorkerComponentStateRepository $repository) {
                            \assert('' !== $jobId);

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::APPLICATION)
                                    ->setState('running')
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::COMPILATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );
                        }
                    ),
                'expectedSerializedJobCreator' => $expectedSerializedJobCreatorCreator([
                    'compilation' => [
                        'state' => 'complete',
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                    ],
                    'execution' => [
                        'state' => 'pending',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => 'pending',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                ]),
            ],
            'worker-job: application running, compilation complete, execution running' => [
                'stagingConfiguration' => $stagingConfiguration
                    ->withWorkerComponentStatesCreator(
                        function (string $jobId, WorkerComponentStateRepository $repository) {
                            \assert('' !== $jobId);

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::APPLICATION)
                                    ->setState('running')
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::COMPILATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EXECUTION)
                                    ->setState('running')
                                    ->setMetaState(new MetaState(false, false))
                            );
                        }
                    ),
                'expectedSerializedJobCreator' => $expectedSerializedJobCreatorCreator([
                    'compilation' => [
                        'state' => 'complete',
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                    ],
                    'execution' => [
                        'state' => 'running',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => 'pending',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                ]),
            ],
            'worker-job: all components complete' => [
                'stagingConfiguration' => $stagingConfiguration
                    ->withWorkerComponentStatesCreator(
                        function (string $jobId, WorkerComponentStateRepository $repository) {
                            \assert('' !== $jobId);

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::APPLICATION)
                                    ->setState('running')
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::COMPILATION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EXECUTION)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );

                            $repository->save(
                                new WorkerComponentState($jobId, WorkerComponentName::EVENT_DELIVERY)
                                    ->setState('complete')
                                    ->setMetaState(new MetaState(true, true))
                            );
                        }
                    ),
                'expectedSerializedJobCreator' => $expectedSerializedJobCreatorCreator([
                    'compilation' => [
                        'state' => 'complete',
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                    ],
                    'execution' => [
                        'state' => 'complete',
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => 'complete',
                        'meta_state' => [
                            'ended' => true,
                            'succeeded' => true,
                        ],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function workerJobCreationFailedDataProvider(): array
    {
        $stagingConfiguration = new StagingConfiguration()
            ->withResultsJobCreator(function (string $jobId, ResultsJobRepository $repository) {
                \assert('' !== $jobId);

                $resultsJob = new ResultsJob(
                    $jobId,
                    md5((string) rand()),
                    'ended',
                    'complete',
                    new MetaState(true, true)
                );

                $repository->save($resultsJob);

                return $resultsJob;
            })
            ->withSerializedSuiteCreator(function (string $jobId, SerializedSuiteRepository $repository) {
                \assert('' !== $jobId);

                $serializedSuite = new SerializedSuite(
                    $jobId,
                    md5((string) rand()),
                    'prepared',
                    new MetaState(true, true),
                );
                $repository->save($serializedSuite);

                return $serializedSuite;
            })
            ->withMachineCreator(function (string $jobId, MachineRepository $repository) {
                \assert('' !== $jobId);

                $machine = new Machine(
                    $jobId,
                    'complete',
                    'end',
                    new MetaState(true, true),
                );
                $machine = $machine->setIp(md5((string) rand()));

                $repository->save($machine);

                return $machine;
            })
        ;

        $expectedSerializedJobCreatorCreator = function (array $creationFailureData) {
            return function (
                JobInterface $job,
                ?SerializedSuite $serializedSuite,
                Machine $machine
            ) use ($creationFailureData) {
                return [
                    'id' => $job->getId(),
                    'suite_id' => $job->getSuiteId(),
                    'maximum_duration_in_seconds' => $job->getMaximumDurationInSeconds(),
                    'created_at' => $job->toArray()['created_at'],
                    'meta_state' => [
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'preparation' => [
                        'state' => 'preparing',
                        'meta_state' => [
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'request_states' => [
                            'results-job' => 'succeeded',
                            'serialized-suite' => 'succeeded',
                            'machine' => 'succeeded',
                            'worker-job' => 'pending',
                        ],
                        'failures' => [],
                    ],
                    'components' => [
                        'results-job' => [
                            'state' => 'ended',
                            'end_state' => 'complete',
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => true,
                            ],
                        ],
                        'serialized-suite' => [
                            'state' => 'prepared',
                            'is_prepared' => true,
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => true,
                            ],
                        ],
                        'machine' => [
                            'state_category' => 'end',
                            'ip_address' => $machine->getIp(),
                            'action_failure' => null,
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => true,
                            ],
                        ],
                        'worker-job' => [
                            'state' => 'failed',
                            'meta_state' => [
                                'ended' => true,
                                'succeeded' => false,
                            ],
                            'creation_failure' => $creationFailureData,
                            'components' => [
                                'compilation' => [
                                    'state' => 'pending',
                                    'meta_state' => [
                                        'ended' => false,
                                        'succeeded' => false,
                                    ],
                                ],
                                'execution' => [
                                    'state' => 'pending',
                                    'meta_state' => [
                                        'ended' => false,
                                        'succeeded' => false,
                                    ],
                                ],
                                'event_delivery' => [
                                    'state' => 'pending',
                                    'meta_state' => [
                                        'ended' => false,
                                        'succeeded' => false,
                                    ],
                                ],
                            ],
                            'preparation' => [
                                'state' => 'pending',
                                'request_state' => 'pending',
                            ],
                            'requests' => [],
                        ],
                    ],
                    'service_requests' => [],
                ];
            };
        };

        return [
            'worker job creation failed; serialized suite read failure' => [
                'stagingConfiguration' => $stagingConfiguration->withWorkerJobCreationFailureCreator(
                    function (string $jobId, WorkerJobCreationFailureRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new WorkerJobCreationFailure(
                                $jobId,
                                WorkerJobCreationStage::SERIALIZED_SUITE_READ,
                                new \Exception(
                                    'exception message',
                                    123
                                )
                            )
                        );
                    }
                ),
                'expectedSerializedJobCreator' => $expectedSerializedJobCreatorCreator([
                    'stage' => WorkerJobCreationStage::SERIALIZED_SUITE_READ->value,
                    'exception' => [
                        'class' => \Exception::class,
                        'message' => 'exception message',
                        'code' => 123,
                    ],
                ]),
            ],
            'worker job creation failed; job creation failure' => [
                'stagingConfiguration' => $stagingConfiguration->withWorkerJobCreationFailureCreator(
                    function (string $jobId, WorkerJobCreationFailureRepository $repository) {
                        \assert('' !== $jobId);

                        $repository->save(
                            new WorkerJobCreationFailure(
                                $jobId,
                                WorkerJobCreationStage::WORKER_JOB_CREATE,
                                new \Exception(
                                    'exception message',
                                    123
                                )
                            )
                        );
                    }
                ),
                'expectedSerializedJobCreator' => $expectedSerializedJobCreatorCreator([
                    'stage' => WorkerJobCreationStage::WORKER_JOB_CREATE->value,
                    'exception' => [
                        'class' => \Exception::class,
                        'message' => 'exception message',
                        'code' => 123,
                    ],
                ]),
            ],
        ];
    }
}
