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
use SmartAssert\UsersSecurityBundle\Security\SymfonyRequestTokenExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

class JobControllerTest extends TestCase
{
    /**
     * @dataProvider createFailureDataProvider
     *
     * @param array<mixed> $expectedResponseData
     */
    public function testCreateFailure(
        Request $request,
        UserInterface $user,
        JobRepository $jobRepository,
        UlidFactory $ulidFactory,
        ResultsClient $resultsClient,
        SymfonyRequestTokenExtractor $tokenExtractor,
        array $expectedResponseData,
    ): void {
        $controller = new JobController();

        $response = $controller->create($request, $user, $jobRepository, $ulidFactory, $resultsClient, $tokenExtractor);

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
            'empty user' => [
                'request' => \Mockery::mock(Request::class),
                'user' => $this->createUser(''),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => \Mockery::mock(UlidFactory::class),
                'resultsClient' => \Mockery::mock(ResultsClient::class),
                'tokenExtractor' => \Mockery::mock(SymfonyRequestTokenExtractor::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'User identifier is empty.',
                ],
            ],
            'empty label generated' => [
                'request' => new Request(request: ['suite_id' => (string) new Ulid()]),
                'user' => $this->createUser($userId),
                'jobRepository' => \Mockery::mock(JobRepository::class),
                'ulidFactory' => $this->createUlidFactory(new EmptyUlidException()),
                'resultsClient' => \Mockery::mock(ResultsClient::class),
                'tokenExtractor' => \Mockery::mock(SymfonyRequestTokenExtractor::class),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Generated job label is an empty string.',
                ],
            ],
            'token extraction failed' => [
                'request' => new Request(request: ['suite_id' => $suiteId]),
                'user' => $this->createUser($userId),
                'jobRepository' => $this->createJobRepository($userId, $suiteId, $label),
                'ulidFactory' => $this->createUlidFactory($label),
                'resultsClient' => \Mockery::mock(ResultsClient::class),
                'tokenExtractor' => $this->createTokenExtractor(null),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Request user token is empty.',
                ],
            ],
            'results service job creation failed' => [
                'request' => new Request(request: ['suite_id' => $suiteId]),
                'user' => $this->createUser($userId),
                'jobRepository' => $this->createJobRepository($userId, $suiteId, $label),
                'ulidFactory' => $this->createUlidFactory($label),
                'resultsClient' => (function () use ($userToken, $label): ResultsClient {
                    $resultsClient = \Mockery::mock(ResultsClient::class);
                    $resultsClient
                        ->shouldReceive('createJob')
                        ->with($userToken, $label)
                        ->andReturnNull()
                    ;

                    return $resultsClient;
                })(),
                'tokenExtractor' => $this->createTokenExtractor($userToken),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Failed creating job in results service.',
                ],
            ],
            'results service response lacking token' => [
                'request' => new Request(request: ['suite_id' => $suiteId]),
                'user' => $this->createUser($userId),
                'jobRepository' => $this->createJobRepository($userId, $suiteId, $label),
                'ulidFactory' => $this->createUlidFactory($label),
                'resultsClient' => (function () use ($userToken, $label): ResultsClient {
                    $resultsClient = \Mockery::mock(ResultsClient::class);
                    $resultsClient
                        ->shouldReceive('createJob')
                        ->with($userToken, $label)
                        ->andReturn(new ResultsJob('non-empty label', ''))
                    ;

                    return $resultsClient;
                })(),
                'tokenExtractor' => $this->createTokenExtractor($userToken),
                'expectedResponseData' => [
                    'type' => 'server_error',
                    'message' => 'Results service job invalid, token missing.',
                ],
            ],
        ];
    }

    private function createUser(string $userId): UserInterface
    {
        $user = \Mockery::mock(UserInterface::class);
        $user
            ->shouldReceive('getUserIdentifier')
            ->andReturn($userId)
        ;

        return $user;
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

    private function createTokenExtractor(?string $token): SymfonyRequestTokenExtractor
    {
        $tokenExtractor = \Mockery::mock(SymfonyRequestTokenExtractor::class);
        $tokenExtractor
            ->shouldReceive('extract')
            ->andReturn($token)
        ;

        return $tokenExtractor;
    }
}
