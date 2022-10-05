<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\JobController;
use App\Entity\Job;
use App\Exception\EmptyUlidException;
use App\Repository\JobRepository;
use App\Services\UlidFactory;
use Monolog\Test\TestCase;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\UsersSecurityBundle\Security\User;

class JobControllerTest extends TestCase
{
    /**
     * @dataProvider createFailureDataProvider
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
        array $expectedResponseData,
    ): void {
        $controller = new JobController();
        $response = $controller->create($suiteId, $user, $jobRepository, $ulidFactory, $resultsClient);

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
        $label = (new UlidFactory())->create();
        $suiteId = (new UlidFactory())->create();
        $userToken = md5((string) rand());

        return [
            'empty label generated' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => $this->createUlidFactory(new EmptyUlidException()),
                'resultsClient' => \Mockery::mock(ResultsClient::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Generated job label is an empty string.',
                ],
            ],
            'results service job creation failed' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($userId, $suiteId, $label),
                'ulidFactory' => $this->createUlidFactory($label),
                'resultsClient' => $this->createResultsClient($userToken, $label, null),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                ],
            ],
            'results service response lacking token' => [
                'suiteId' => $suiteId,
                'user' => new User($userId, $userToken),
                'jobRepository' => $this->createJobRepository($userId, $suiteId, $label),
                'ulidFactory' => $this->createUlidFactory($label),
                'resultsClient' => $this->createResultsClient(
                    $userToken,
                    $label,
                    new ResultsJob('non-empty label', '')
                ),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Results service job invalid, token missing.',
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

    private function createJobRepository(string $userId, string $suiteId, string $label): JobRepository
    {
        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('add')
            ->withArgs(function (Job $job) use ($userId, $suiteId, $label) {
                self::assertSame($userId, $job->getUserId());
                self::assertSame($suiteId, $job->getSuiteId());
                self::assertSame($label, $job->getLabel());

                return true;
            })
        ;

        return $jobRepository;
    }

    private function createResultsClient(string $userToken, string $label, ?ResultsJob $job): ResultsClient
    {
        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('createJob')
            ->with($userToken, $label)
            ->andReturn($job)
        ;

        return $resultsClient;
    }
}
