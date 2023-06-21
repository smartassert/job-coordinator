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

        return [
            'no remote requests, no results job, no serialized suite, no machine' => [
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
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
                'resultsJobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
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
                                md5((string) rand()),
                                RemoteRequestFailureType::NETWORK,
                                6,
                                'unable to resolve host "sources.example.com"'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_GET, 1))
                            ->setState(RequestState::FAILED)
                            ->setFailure(new RemoteRequestFailure(
                                md5((string) rand()),
                                RemoteRequestFailureType::HTTP,
                                503,
                                'service unavailable'
                            )),
                        (new RemoteRequest($job->id, RemoteRequestType::SERIALIZED_SUITE_GET, 2))
                            ->setState(RequestState::SUCCEEDED),
                    ];
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
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
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'resultsJobCreator' => function (Job $job, ResultsJobRepository $resultsJobRepository) {
                    $resultsJob = new ResultsJob($job->id, md5((string) rand()), md5((string) rand()), null);
                    $resultsJobRepository->save($resultsJob);

                    return $resultsJob;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'state' => $resultsJob->getState(),
                            'end_state' => null,
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has results state, has results end state' => [
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'resultsJobCreator' => function (Job $job, ResultsJobRepository $resultsJobRepository) {
                    $resultsJob = new ResultsJob(
                        $job->id,
                        md5((string) rand()),
                        md5((string) rand()),
                        md5((string) rand())
                    );
                    $resultsJobRepository->save($resultsJob);

                    return $resultsJob;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (Job $job, ResultsJob $resultsJob) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'results_job' => [
                            'state' => $resultsJob->getState(),
                            'end_state' => $resultsJob->getEndState(),
                        ],
                        'service_requests' => [],
                    ];
                },
            ],
            'has serialized suite' => [
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'resultsJobCreator' => function () {
                    return null;
                },
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
                'machineCreator' => function () {
                    return null;
                },
                'expectedSerializedJobCreator' => function (
                    Job $job,
                    ?ResultsJob $resultsJob,
                    SerializedSuite $serializedSuite
                ) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'service_requests' => [],
                        'serialized_suite' => [
                            'id' => $serializedSuite->getId(),
                            'state' => 'prepared',
                        ],
                    ];
                },
            ],
            'has machine' => [
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'resultsJobCreator' => function () {
                    return null;
                },
                'serializedSuiteCreator' => function () {
                    return null;
                },
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
                        'machine' => [
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
