<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\SourcesClient\FileClient;
use SmartAssert\SourcesClient\Model\Suite;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use SmartAssert\TestAuthenticationProviderBundle\UserProvider;
use SmartAssert\UsersClient\Model\User;

abstract class AbstractCreateJobSuccessTest extends AbstractApplicationTest
{
    private static ResponseInterface $createResponse;
    private static User $user;
    private static Suite $suite;

    /**
     * @var array<mixed>
     */
    private static array $createResponseData;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $userProvider = self::getContainer()->get(UserProvider::class);
        \assert($userProvider instanceof UserProvider);
        self::$user = $userProvider->get('user@example.com');

        $sourceClient = self::getContainer()->get(SourceClient::class);
        \assert($sourceClient instanceof SourceClient);
        $source = $sourceClient->createFileSource($apiToken, md5((string) rand()));

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);
        $fileClient->add($apiToken, $source->getId(), 'test1.yaml', 'test 1 contents');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);
        self::$suite = $suiteClient->create($apiToken, $source->getId(), md5((string) rand()), ['test1.yaml']);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        self::$createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, self::$suite->getId());

        self::assertSame(200, self::$createResponse->getStatusCode());
        self::assertSame('application/json', self::$createResponse->getHeaderLine('content-type'));

        $responseData = json_decode(self::$createResponse->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::$createResponseData = $responseData;
    }

    public function testJobIsCreated(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(1, $jobRepository->findAll());

        $jobs = $jobRepository->findAll();
        $job = $jobs[0] ?? null;
        self::assertInstanceOf(Job::class, $job);
    }

    public function testJobUser(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertSame($job->userId, self::$user->id);
    }

    public function testJobResultsTokenIsSet(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertNotNull($job->resultsToken);
    }

    public function testJobResponseData(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertSame(
            [
                'job' => [
                    'id' => $job->id,
                    'suite_id' => $job->suiteId,
                    'serialized_suite_id' => $job->serializedSuiteId,
                ],
                'machine' => [
                    'id' => $job->id,
                    'state' => 'create/received',
                    'state_category' => 'pre_active',
                    'ip_addresses' => [],
                ],
            ],
            self::$createResponseData,
        );
    }

    private function getJob(): ?Job
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobs = $jobRepository->findAll();
        $job = $jobs[0] ?? null;

        return $job instanceof Job ? $job : null;
    }
}
