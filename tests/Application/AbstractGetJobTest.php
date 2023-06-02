<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\UlidFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

abstract class AbstractGetJobTest extends AbstractApplicationTest
{
    private string $jobId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobId = (string) new Ulid();
    }

    /**
     * @dataProvider getBadMethodDataProvider
     */
    public function testGetBadMethod(string $method): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $response = self::$staticApplicationClient->makeGetJobRequest($apiToken, $this->jobId, $method);

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public function getBadMethodDataProvider(): array
    {
        return [
            'PUT' => [
                'method' => 'PUT',
            ],
            'DELETE' => [
                'method' => 'DELETE',
            ],
        ];
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testGetUnauthorizedUser(?string $apiToken): void
    {
        $response = self::$staticApplicationClient->makeGetJobRequest($apiToken, $this->jobId);

        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public function unauthorizedUserDataProvider(): array
    {
        return [
            'no user token' => [
                'apiToken' => null,
            ],
            'empty user token' => [
                'apiToken' => '',
            ],
            'non-empty invalid user token' => [
                'apiToken' => 'invalid api token',
            ],
        ];
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param callable(Job): RemoteRequest[] $remoteRequestsCreator
     * @param callable(Job): array<mixed>    $expectedSerializedJobCreator
     */
    public function testGetSuccess(callable $remoteRequestsCreator, callable $expectedSerializedJobCreator): void
    {
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

        self::assertEquals($expectedSerializedJobCreator($job), $responseData);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no remote requests' => [
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'serialized_suite' => [
                            'id' => null,
                            'state' => null,
                            'request_state' => 'unknown',
                        ],
                        'machine' => [
                            'state_category' => null,
                            'ip_address' => null,
                            'request_state' => 'unknown',
                        ],
                        'results_job' => [
                            'request_state' => 'unknown',
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
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'serialized_suite' => [
                            'id' => null,
                            'state' => null,
                            'request_state' => 'unknown',
                        ],
                        'machine' => [
                            'state_category' => null,
                            'ip_address' => null,
                            'request_state' => 'unknown',
                        ],
                        'results_job' => [
                            'request_state' => 'unknown',
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
            'results/create success, serialized-suite/create success, serialized-suite/get failure and success' => [
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
                'expectedSerializedJobCreator' => function (Job $job) {
                    return [
                        'id' => $job->id,
                        'suite_id' => $job->suiteId,
                        'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                        'serialized_suite' => [
                            'id' => null,
                            'state' => null,
                            'request_state' => 'unknown',
                        ],
                        'machine' => [
                            'state_category' => null,
                            'ip_address' => null,
                            'request_state' => 'unknown',
                        ],
                        'results_job' => [
                            'request_state' => 'unknown',
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
        ];
    }
}
