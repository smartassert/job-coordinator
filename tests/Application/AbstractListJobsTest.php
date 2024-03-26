<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use App\Tests\Services\ApplicationClient\Client as ApplicationClient;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-type JobsSetupResult array{api_token: non-empty-string, job_ids: string[], suite_id: non-empty-string}
 */
abstract class AbstractListJobsTest extends AbstractApplicationTest
{
    private string $suiteId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suiteId = (string) new Ulid();
    }

    /**
     * @dataProvider getBadMethodDataProvider
     */
    public function testListBadMethod(string $method): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $response = self::$staticApplicationClient->makeListJobsRequest($apiToken, $this->suiteId, $method);

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public function getBadMethodDataProvider(): array
    {
        return [
            'POST' => [
                'method' => 'POST',
            ],
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
    public function testListUnauthorizedUser(?string $apiToken): void
    {
        $response = self::$staticApplicationClient->makeListJobsRequest($apiToken, $this->suiteId);

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
     * @dataProvider listSuccessDataProvider
     *
     * @param callable(ApiTokenProvider, ApplicationClient): JobsSetupResult $setup
     * @param callable(Job[]): array<mixed>                                  $expectedCreator
     */
    public function testListSuccess(callable $setup, callable $expectedCreator): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);

        $setupResult = $setup($apiTokenProvider, self::$staticApplicationClient);
        $createdJobIds = $setupResult['job_ids'];

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobs = $jobRepository->findAll();

        $filteredJobs = [];
        foreach ($jobs as $job) {
            if (in_array($job->id, $createdJobIds)) {
                $filteredJobs[] = $job;
            }
        }

        $response = self::$staticApplicationClient->makeListJobsRequest(
            $setupResult['api_token'],
            $setupResult['suite_id']
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertSame($expectedCreator($filteredJobs), $responseData);
    }

    /**
     * @return array<mixed>
     */
    public function listSuccessDataProvider(): array
    {
        return [
            'no jobs' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider) {
                    return [
                        'api_token' => $apiTokenProvider->get('user1@example.com'),
                        'job_ids' => [],
                        'suite_id' => (string) new Ulid(),
                    ];
                },
                'expectedCreator' => function () {
                    return [];
                },
            ],
            'single job for user' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId = (string) new Ulid();

                    $apiToken = $apiTokenProvider->get('user1@example.com');

                    $jobIds = [];

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId, 123)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId,
                    ];
                },
                'expectedCreator' => function (array $jobs) {
                    $expectedJobs = [
                        $jobs[0],
                    ];

                    $expected = [];
                    foreach ($expectedJobs as $expectedJob) {
                        \assert($expectedJob instanceof Job);

                        $expected[] = $expectedJob->toArray();
                    }

                    return $expected;
                },
            ],
            'multiple jobs for user across suites (1)' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $apiToken = $apiTokenProvider->get('user1@example.com');

                    $jobIds = [];

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 123)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId2, 456)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 789)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId1,
                    ];
                },
                'expectedCreator' => function (array $jobs) {
                    $expectedJobs = [
                        $jobs[2],
                        $jobs[0],
                    ];

                    $expected = [];
                    foreach ($expectedJobs as $expectedJob) {
                        \assert($expectedJob instanceof Job);

                        $expected[] = $expectedJob->toArray();
                    }

                    return $expected;
                },
            ],
            'multiple jobs for user across suites (2)' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $apiToken = $apiTokenProvider->get('user1@example.com');

                    $jobIds = [];

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 123)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId2, 456)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 789)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId2,
                    ];
                },
                'expectedCreator' => function (array $jobs) {
                    $expectedJobs = [
                        $jobs[1],
                    ];

                    $expected = [];
                    foreach ($expectedJobs as $expectedJob) {
                        \assert($expectedJob instanceof Job);

                        $expected[] = $expectedJob->toArray();
                    }

                    return $expected;
                },
            ],
            'multiple jobs for user across suites for multiple users' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $user1ApiToken = $apiTokenProvider->get('user1@example.com');
                    $user2ApiToken = $apiTokenProvider->get('user2@example.com');

                    $jobIds = [];

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId1, 123)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user2ApiToken, $suiteId1, 456)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId2, 789)
                    );

                    $jobIds[] = $this->getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId1, 321)
                    );

                    return [
                        'api_token' => $user1ApiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId1,
                    ];
                },
                'expectedCreator' => function (array $jobs) {
                    $expectedJobs = [
                        $jobs[3],
                        $jobs[0],
                    ];

                    $expected = [];
                    foreach ($expectedJobs as $expectedJob) {
                        \assert($expectedJob instanceof Job);

                        $expected[] = $expectedJob->toArray();
                    }

                    return $expected;
                },
            ],
        ];
    }

    private function getJobIdFromResponse(ResponseInterface $response): string
    {
        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData));

        $id = $responseData['id'] ?? null;
        \assert(is_string($id));

        return $id;
    }
}
