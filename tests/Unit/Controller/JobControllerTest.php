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
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\UsersSecurityBundle\Security\User;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Exception\CreateMachineException;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\HttpFoundation\JsonResponse;

class JobControllerTest extends TestCase
{
    private JobController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new JobController();
    }

    public function testCreateFailureUnableToGenerateJobId(): void
    {
        $ulidFactory = \Mockery::mock(UlidFactory::class);
        $ulidFactory
            ->shouldReceive('create')
            ->andThrow(new EmptyUlidException())
        ;

        $response = $this->controller->create(
            'suite id value',
            new User((new UlidFactory())->create(), md5((string) rand())),
            \Mockery::mock(JobRepository::class),
            $ulidFactory,
            \Mockery::mock(ResultsClient::class),
            new ErrorResponseFactory(),
            \Mockery::mock(WorkerManagerClient::class),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertIsArray($responseData);
        self::assertEquals(
            [
                'type' => 'server_error',
                'message' => 'Generated job id is an empty string.',
            ],
            $responseData
        );
    }

    /**
     * @dataProvider createFailureResultsServiceJobFailureDataProvider
     * @dataProvider createFailureWorkerManagerMachineCreateRequestFailureDataProvider
     *
     * @param array<mixed> $expectedResponseData
     */
    public function testCreateFailure(
        string $jobId,
        ResultsJob|\Exception $resultsClientOutcome,
        Machine|\Exception|null $workerManagerClientOutcome,
        array $expectedResponseData,
    ): void {
        $suiteId = (new UlidFactory())->create();
        $userId = (new UlidFactory())->create();
        $userToken = md5((string) rand());
        $user = new User($userId, $userToken);

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('add')
            ->withArgs(function (Job $job) use ($jobId, $user, $suiteId) {
                self::assertSame($jobId, $job->id);
                self::assertSame($user->getUserIdentifier(), $job->userId);
                self::assertSame($suiteId, $job->suiteId);

                return true;
            })
        ;

        $ulidFactory = \Mockery::mock(UlidFactory::class);
        $ulidFactory
            ->shouldReceive('create')
            ->andReturn($jobId)
        ;

        $resultsClient = $this->createResultsClient($user->getSecurityToken(), $jobId, $resultsClientOutcome);

        $workerManagerClient = null === $workerManagerClientOutcome
            ? \Mockery::mock(WorkerManagerClient::class)
            : $this->createWorkerManagerClient($user->getSecurityToken(), $jobId, $workerManagerClientOutcome);

        $response = $this->controller->create(
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
    public function createFailureResultsServiceJobFailureDataProvider(): array
    {
        $id = (new UlidFactory())->create();

        return [
            'results service job creation failed, invalid response status code, default reason phrase' => [
                'jobId' => $id,
                'resultsClientOutcome' => new NonSuccessResponseException(
                    new Response(503),
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new NonSuccessResponseException(
                    new Response(503, [], '', '1.1', 'Maintenance ...'),
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new InvalidResponseTypeException(
                    new Response(
                        200,
                        [
                            'content-type' => 'text/html',
                        ],
                        '<body />'
                    ),
                    JsonResponse::class,
                    Response::class
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new InvalidResponseDataException(
                    'array',
                    'int',
                    new Response(
                        200,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode(123)
                    ),
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new InvalidModelDataException(
                    new Response(
                        500,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode([])
                    ),
                    ResultsJob::class,
                    []
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new InvalidModelDataException(
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
                ),
                'workerManagerClientOutcome' => null,
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
                'jobId' => $id,
                'resultsClientOutcome' => new ResultsJob('non-empty label', ''),
                'workerManagerClientOutcome' => null,
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
        $id = (new UlidFactory())->create();
        $resultsJobToken = md5((string) rand());

        $resultsJob = new ResultsJob($id, $resultsJobToken);

        return [
            'worker manager service create failed, invalid response status code, default reason phrase' => [
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new NonSuccessResponseException(
                    new Response(503),
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
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new NonSuccessResponseException(
                    new Response(503, [], '', '1.1', 'Maintenance ...'),
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
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new InvalidResponseTypeException(
                    new Response(
                        200,
                        [
                            'content-type' => 'text/html',
                        ],
                        '<body />'
                    ),
                    JsonResponse::class,
                    Response::class
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
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new InvalidResponseDataException(
                    'array',
                    'int',
                    new Response(
                        200,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode(123)
                    ),
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
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new InvalidModelDataException(
                    new Response(
                        500,
                        [
                            'content-type' => 'application/json',
                        ],
                        (string) json_encode([])
                    ),
                    ResultsJob::class,
                    []
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
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new InvalidModelDataException(
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
            'worker manager service create failed, job already exists with id' => [
                'jobId' => $id,
                'resultsClientOutcome' => $resultsJob,
                'workerManagerClientOutcome' => new CreateMachineException(
                    'id taken',
                    100
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed requesting worker machine creation.',
                    'context' => [
                        'exception' => [
                            'message' => 'id taken',
                            'code' => 100,
                        ],
                    ],
                ],
            ],
        ];
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
