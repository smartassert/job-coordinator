<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Model\JobInterface;
use App\Repository\JobRepository;
use App\Services\JobStore;
use App\Tests\Services\ApplicationClient\Client as ApplicationClient;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('getBadMethodDataProvider')]
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
    public static function getBadMethodDataProvider(): array
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

    #[DataProvider('unauthorizedUserDataProvider')]
    public function testListUnauthorizedUser(?string $apiToken): void
    {
        $response = self::$staticApplicationClient->makeListJobsRequest($apiToken, $this->suiteId);

        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public static function unauthorizedUserDataProvider(): array
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
     * @param callable(ApiTokenProvider, ApplicationClient): JobsSetupResult $setup
     * @param callable(Job[], JobStore): JobInterface[]                      $expectedCreator
     */
    #[DataProvider('listSuccessDataProvider')]
    public function testListSuccess(callable $setup, callable $expectedCreator): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);

        $setupResult = $setup($apiTokenProvider, self::$staticApplicationClient);
        $createdJobIds = $setupResult['job_ids'];

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobs = $jobRepository->findBy(
            [],
            [
                'id' => 'ASC',
            ]
        );

        $filteredJobs = [];
        foreach ($jobs as $job) {
            if (in_array($job->getId(), $createdJobIds)) {
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

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        self::assertSame($expectedCreator($filteredJobs, $jobStore), $responseData);
    }

    /**
     * @return array<mixed>
     */
    public static function listSuccessDataProvider(): array
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

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId, 123)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId,
                    ];
                },
                'expectedCreator' => function (array $jobs, JobStore $jobStore) {
                    $serializedJobs = [];
                    $job = $jobStore->retrieve($jobs[0]->getId());
                    if (null !== $job) {
                        $serializedJobs[] = $job->toArray();
                    }

                    return $serializedJobs;
                },
            ],
            'multiple jobs for user across suites (1)' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $apiToken = $apiTokenProvider->get('user1@example.com');

                    $jobIds = [];

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 123)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId2, 456)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 789)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId1,
                    ];
                },
                'expectedCreator' => function (array $jobs, JobStore $jobStore) {
                    $serializedJobs = [];
                    foreach ([$jobs[2], $jobs[0]] as $jobEntity) {
                        $job = $jobStore->retrieve($jobEntity->getId());
                        if (null !== $job) {
                            $serializedJobs[] = $job->toArray();
                        }
                    }

                    return $serializedJobs;
                },
            ],
            'multiple jobs for user across suites (2)' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $apiToken = $apiTokenProvider->get('user1@example.com');

                    $jobIds = [];

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 123)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId2, 456)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($apiToken, $suiteId1, 789)
                    );

                    return [
                        'api_token' => $apiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId2,
                    ];
                },
                'expectedCreator' => function (array $jobs, JobStore $jobStore) {
                    $serializedJobs = [];
                    $job = $jobStore->retrieve($jobs[1]->getId());
                    if (null !== $job) {
                        $serializedJobs[] = $job->toArray();
                    }

                    return $serializedJobs;
                },
            ],
            'multiple jobs for user across suites for multiple users' => [
                'setup' => function (ApiTokenProvider $apiTokenProvider, ApplicationClient $applicationClient) {
                    $suiteId1 = (string) new Ulid();
                    $suiteId2 = (string) new Ulid();

                    $user1ApiToken = $apiTokenProvider->get('user1@example.com');
                    $user2ApiToken = $apiTokenProvider->get('user2@example.com');

                    $jobIds = [];

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId1, 123)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user2ApiToken, $suiteId1, 456)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId2, 789)
                    );

                    $jobIds[] = self::getJobIdFromResponse(
                        $applicationClient->makeCreateJobRequest($user1ApiToken, $suiteId1, 321)
                    );

                    return [
                        'api_token' => $user1ApiToken,
                        'job_ids' => $jobIds,
                        'suite_id' => $suiteId1,
                    ];
                },
                'expectedCreator' => function (array $jobs, JobStore $jobStore) {
                    $serializedJobs = [];
                    foreach ([$jobs[3], $jobs[0]] as $jobEntity) {
                        $job = $jobStore->retrieve($jobEntity->getId());
                        if (null !== $job) {
                            $serializedJobs[] = $job->toArray();
                        }
                    }

                    return $serializedJobs;
                },
            ],
        ];
    }

    private static function getJobIdFromResponse(ResponseInterface $response): string
    {
        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData));

        $id = $responseData['id'] ?? null;
        \assert(is_string($id));

        return $id;
    }
}
