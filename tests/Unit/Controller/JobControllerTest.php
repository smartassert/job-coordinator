<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\JobController;
use App\Entity\Job;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Services\ErrorResponseFactory;
use App\Services\UlidFactory;
use GuzzleHttp\Psr7\Response;
use Monolog\Test\TestCase;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseContentException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\UsersSecurityBundle\Security\User;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;

class JobControllerTest extends TestCase
{
    /**
     * @dataProvider createFailureDataProvider
     * @dataProvider createFailureResultsServiceJobFailureDataProvider
     * @dataProvider createFailureWorkerManagerMachineCreateRequestFailureDataProvider
     *
     * @param non-empty-string $suiteId
     * @param array<mixed>     $expectedResponseData
     */
    public function testCreateFailure(
        string $suiteId,
        User $user,
        JobRepository $jobRepository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
        WorkerManagerClient $workerManagerClient,
        array $expectedResponseData,
    ): void {
        $controller = new JobController();
        $response = $controller->create(
            $suiteId,
            $user,
            $jobRepository,
            $ulidFactory,
            $resultsClient,
            new ErrorResponseFactory(),
            $workerManagerClient,
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertIsArray($responseData);
        self::assertEquals($expectedResponseData, $responseData);
    }

    /**
     * @return array<mixed>
     */
    public function createFailureDataProvider(): array
    {
        $emptyUlidFactory = \Mockery::mock(UlidFactory::class);
        $emptyUlidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        $userId = (new UlidFactory())->create();
        $suiteId = (new UlidFactory())->create();
        $userToken = md5((string) rand());

        return [
            'empty id generated' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => $this->createUlidFactory(new EmptyUlidException()),
                'resultsClient' => \Mockery::mock(ResultsClient::class),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Generated job id is an empty string.',
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function createFailureResultsServiceJobFailureDataProvider(): array
    {
        $emptyUlidFactory = \Mockery::mock(UlidFactory::class);
        $emptyUlidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        $id = (new UlidFactory())->create();
        $userId = (new UlidFactory())->create();
        $suiteId = (new UlidFactory())->create();
        $userToken = md5((string) rand());

        return [
            'results service job creation failed, invalid response status code, default reason phrase' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new NonSuccessResponseException(
                    new Response(503),
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 503,
                            'content_type' => '',
                            'data' => 'Service Unavailable',
                        ],
                    ],
                ],
            ],
            'results service job creation failed, invalid response status code, custom reason phrase' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new NonSuccessResponseException(
                    new Response(503, [], '', '1.1', 'Maintenance ...'),
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 503,
                            'content_type' => '',
                            'data' => 'Maintenance ...',
                        ],
                    ],
                ],
            ],
            'results service job creation failed, invalid response content type' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new InvalidResponseContentException(
                    new Response(
                        200,
                        [
                            'content-type' => 'text/html',
                        ],
                        '<body />'
                    ),
                    'application/json',
                    'text/html'
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'text/html',
                            'data' => '<body />',
                        ],
                    ],
                ],
            ],
            'results service job creation failed, invalid response data type' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new InvalidResponseDataException(
                    'array',
                    'int',
                    new Response(
                        200,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode(123)
                    ),
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'application/json',
                            'data' => '123',
                        ],
                    ],
                ],
            ],
            'results service job creation failed, invalid empty results service response payload' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new InvalidModelDataException(
                    new Response(
                        500,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode([])
                    ),
                    ResultsJob::class,
                    []
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 500,
                            'content_type' => 'application/json',
                            'data' => [],
                        ],
                    ],
                ],
            ],
            'results service job creation failed, invalid non-empty results service response payload' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, new InvalidModelDataException(
                    new Response(
                        200,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode([
                            'key1' => 'value1',
                            'key2' => 'value2',
                        ]),
                    ),
                    ResultsJob::class,
                    [
                        'key1' => 'value1',
                        'key2' => 'value2',
                    ]
                )),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'application/json',
                            'data' => [
                                'key1' => 'value1',
                                'key2' => 'value2',
                            ],
                        ],
                    ],
                ],
            ],
            'results service response lacking token' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient(
                    $userToken,
                    $id,
                    new ResultsJob('non-empty label', '')
                ),
                'workerManagerClient' => \Mockery::mock(WorkerManagerClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Results service job invalid, token missing.',
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function createFailureWorkerManagerMachineCreateRequestFailureDataProvider(): array
    {
        $emptyUlidFactory = \Mockery::mock(UlidFactory::class);
        $emptyUlidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        $id = (new UlidFactory())->create();
        $userId = (new UlidFactory())->create();
        $suiteId = (new UlidFactory())->create();
        $userToken = md5((string) rand());
        $resultsJobToken = md5((string) rand());

        $resultsJob = new ResultsJob($id, $resultsJobToken);

        return [
            'worker manager service create failed, invalid response status code, default reason phrase' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new NonSuccessResponseException(
                        new Response(503),
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 503,
                            'content_type' => '',
                            'data' => 'Service Unavailable',
                        ],
                    ],
                ],
            ],
            'worker manager service create failed, invalid response status code, custom reason phrase' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new NonSuccessResponseException(
                        new Response(503, [], '', '1.1', 'Maintenance ...'),
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 503,
                            'content_type' => '',
                            'data' => 'Maintenance ...',
                        ],
                    ],
                ],
            ],
            'worker manager service create failed, invalid response content type' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new InvalidResponseContentException(
                        new Response(
                            200,
                            [
                                'content-type' => 'text/html',
                            ],
                            '<body />'
                        ),
                        'application/json',
                        'text/html'
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'text/html',
                            'data' => '<body />',
                        ],
                    ],
                ],
            ],
            'worker manager service create failed, invalid response data type' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new InvalidResponseDataException(
                        'array',
                        'int',
                        new Response(
                            200,
                            [
                                'content-type' => 'application/json',
                            ],
                            (string) json_encode(123)
                        ),
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'application/json',
                            'data' => '123',
                        ],
                    ],
                ],
            ],
            'worker manager service create failed, invalid empty results service response payload' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new InvalidModelDataException(
                        new Response(
                            500,
                            [
                                'content-type' => 'application/json',
                            ],
                            (string) json_encode([])
                        ),
                        ResultsJob::class,
                        []
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 500,
                            'content_type' => 'application/json',
                            'data' => [],
                        ],
                    ],
                ],
            ],
            'worker manager service create failed, invalid non-empty results service response payload' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($id, $userId, $suiteId),
                'ulidFactory' => $this->createUlidFactory($id),
                'resultsClient' => $this->createResultsClient($userToken, $id, $resultsJob),
                'workerManagerClient' => $this->createWorkerManagerClient(
                    $userToken,
                    $id,
                    new InvalidModelDataException(
                        new Response(
                            200,
                            [
                                'content-type' => 'application/json',
                            ],
                            (string) json_encode([
                                'key1' => 'value1',
                                'key2' => 'value2',
                            ]),
                        ),
                        ResultsJob::class,
                        [
                            'key1' => 'value1',
                            'key2' => 'value2',
                        ]
                    )
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'service_response' => [
                            'status_code' => 200,
                            'content_type' => 'application/json',
                            'data' => [
                                'key1' => 'value1',
                                'key2' => 'value2',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createUlidFactory(string|\Exception $outcome): UlidFactory
    {
        $ulidFactory = \Mockery::mock(UlidFactory::class);

        $createCall = $ulidFactory->shouldReceive('create');
        if ($outcome instanceof \Exception) {
            $createCall->andThrow($outcome);
        } else {
            $createCall->andReturn($outcome);
        }

        return $ulidFactory;
    }

    private function createJobRepository(string $id, string $userId, string $suiteId): JobRepository
    {
        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('add')
            ->withArgs(function (Job $job) use ($userId, $suiteId, $id) {
                self::assertSame($id, $job->getId());
                self::assertSame($userId, $job->getUserId());
                self::assertSame($suiteId, $job->getSuiteId());

                return true;
            })
        ;

        return $jobRepository;
    }

    private function createResultsClient(string $userToken, string $id, ResultsJob|\Exception $outcome): ResultsClient
    {
        $resultsClient = \Mockery::mock(ResultsClient::class);

        $expectation = $resultsClient
            ->shouldReceive('createJob')
            ->with($userToken, $id)
        ;

        if ($outcome instanceof ResultsJob) {
            $expectation->andReturn($outcome);
        } else {
            $expectation->andThrow($outcome);
        }

        return $resultsClient;
    }

    private function createWorkerManagerClient(
        string $userToken,
        string $id,
        Machine|\Exception $outcome
    ): WorkerManagerClient {
        $resultsClient = \Mockery::mock(WorkerManagerClient::class);

        $expectation = $resultsClient
            ->shouldReceive('createMachine')
            ->with($userToken, $id)
        ;

        if ($outcome instanceof Machine) {
            $expectation->andReturn($outcome);
        } else {
            $expectation->andThrow($outcome);
        }

        return $resultsClient;
    }
}
