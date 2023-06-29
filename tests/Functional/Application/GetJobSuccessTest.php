<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\UlidFactory;
use App\Tests\Application\AbstractApplicationTest;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class GetJobSuccessTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    /**
     * @dataProvider getDataProvider
     *
     * @param callable(Job): RemoteRequest[]                                       $remoteRequestsCreator
     * @param callable(Job, ResultsJobRepository): ?ResultsJob                     $resultsJobCreator
     * @param callable(Job, SerializedSuiteRepository): ?SerializedSuite           $serializedSuiteCreator
     * @param callable(Job, MachineRepository): ?Machine                           $machineCreator
     * @param callable(Job, ?ResultsJob, ?SerializedSuite, ?Machine): array<mixed> $expectedSerializedJobCreator
     */
    public function testGetSuccess(
        callable $remoteRequestsCreator,
        callable $resultsJobCreator,
        callable $serializedSuiteCreator,
        callable $machineCreator,
        callable $expectedSerializedJobCreator
    ): void {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $ulidFactory = self::getContainer()->get(UlidFactory::class);
        \assert($ulidFactory instanceof UlidFactory);

        $suiteId = $ulidFactory->create();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        foreach ($jobRepository->findAll() as $job) {
            $entityManager->remove($job);
            $entityManager->flush();
        }

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        self::assertCount(0, $jobRepository->findAll());

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

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $remoteRequest) {
            $remoteRequestRepository->remove($remoteRequest);
        }

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
    public function getDataProvider(): array
    {
        $serializedSuiteId = md5((string) rand());

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
            'no remote requests, no results job, no serialized suite, no machine' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'results/create only' => [
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::REQUESTING),
                    ];
                },
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
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
            'results/create success, serialized-suite/create success, serialized-suite/g failure and success' => [
                'remoteRequestsCreator' => function (Job $job) {
                    return [
                        (new RemoteRequest($job->id, RemoteRequestType::RESULTS_CREATE, 0))
                            ->setState(RequestState::SUCCEEDED),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_CREATE, 0))
                            ->setState(RequestState::SUCCEEDED),
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
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
                        ],
                        'service_requests' => [
                            [
                                'type' => 'results/create',
                                'attempts' => [
                                    [
                                        'state' => 'succeeded',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'serialized-suite/create',
                                'attempts' => [
                                    [
                                        'state' => 'succeeded',
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
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => RequestState::SUCCEEDED->value,
                            ],
                            'state' => $resultsJob->getState(),
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
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
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => RequestState::SUCCEEDED->value,
                            ],
                            'state' => $resultsJob->getState(),
                            'end_state' => $resultsJob->getEndState(),
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
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
                ) use (
                    $serializedSuiteId
                ) {
                    $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, 'prepared');
                    $serializedSuiteRepository->save($serializedSuite);

                    return $serializedSuite;
                },
                'machineCreator' => $nullCreator,
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => RequestState::SUCCEEDED->value,
                            ],
                            'state' => 'prepared',
                        ],
                        'machine' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state_category' => null,
                            'ip_address' => null,
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has machine' => [
                'remoteRequestsCreator' => $emptyRemoteRequestsCreator,
                'resultsJobCreator' => $nullCreator,
                'serializedSuiteCreator' => $nullCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, md5((string) rand()), md5((string) rand()));
                    $machine = $machine->setIp(md5((string) rand()));

                    $machineRepository->save($machine);

                    return $machine;
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
                        'results_job' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                            'end_state' => null,
                        ],
                        'serialized_suite' => [
                            'request' => [
                                'state' => 'pending',
                            ],
                            'state' => null,
                        ],
                        'machine' => [
                            'request' => [
                                'state' => RequestState::SUCCEEDED->value,
                            ],
                            'state_category' => $machine->getStateCategory(),
                            'ip_address' => $machine->getIp(),
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
        ];
    }
}
