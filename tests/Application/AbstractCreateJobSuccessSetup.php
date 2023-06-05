<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\SourcesClient\Model\Suite;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use SmartAssert\TestAuthenticationProviderBundle\UserProvider;
use SmartAssert\UsersClient\Model\User;

abstract class AbstractCreateJobSuccessSetup extends AbstractApplicationTest
{
    protected static ResponseInterface $createResponse;
    protected static User $user;
    protected static Suite $suite;

    /**
     * @var non-empty-string
     */
    protected static string $apiToken;

    /**
     * @var array<mixed>
     */
    protected static array $createResponseData;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        self::$apiToken = $apiTokenProvider->get('user@example.com');

        $userProvider = self::getContainer()->get(UserProvider::class);
        \assert($userProvider instanceof UserProvider);
        self::$user = $userProvider->get('user@example.com');

        $sourceClient = self::getContainer()->get(SourceClient::class);
        \assert($sourceClient instanceof SourceClient);
        $source = $sourceClient->createFileSource(self::$apiToken, md5((string) rand()));

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);
        self::$suite = $suiteClient->create(self::$apiToken, $source->getId(), md5((string) rand()), ['test1.yaml']);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        self::$createResponse = self::$staticApplicationClient->makeCreateJobRequest(
            self::$apiToken,
            self::$suite->getId(),
            600
        );

        self::assertSame(200, self::$createResponse->getStatusCode());
        self::assertSame('application/json', self::$createResponse->getHeaderLine('content-type'));

        $responseData = json_decode(self::$createResponse->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::$createResponseData = $responseData;
    }

    protected function getJob(): ?Job
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobs = $jobRepository->findAll();
        $job = $jobs[0] ?? null;

        return $job instanceof Job ? $job : null;
    }
}
