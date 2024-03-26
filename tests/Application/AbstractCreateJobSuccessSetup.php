<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use SmartAssert\TestAuthenticationProviderBundle\UserProvider;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-import-type User from UserProvider
 */
abstract class AbstractCreateJobSuccessSetup extends AbstractApplicationTest
{
    protected static ResponseInterface $createResponse;

    /**
     * @var User
     */
    protected static array $user;

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
        self::$apiToken = $apiTokenProvider->get('user1@example.com');

        $userProvider = self::getContainer()->get(UserProvider::class);
        \assert($userProvider instanceof UserProvider);
        self::$user = $userProvider->get('user1@example.com');

        self::$createResponse = self::$staticApplicationClient->makeCreateJobRequest(
            self::$apiToken,
            static::createSuiteId(),
            600
        );

        self::assertSame(200, self::$createResponse->getStatusCode());
        self::assertSame('application/json', self::$createResponse->getHeaderLine('content-type'));

        $responseData = json_decode(self::$createResponse->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::$createResponseData = $responseData;
    }

    /**
     * @return non-empty-string
     */
    protected static function createSuiteId(): string
    {
        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        return $suiteId;
    }

    protected function getJob(): ?Job
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        return $jobRepository->find(self::$createResponseData['id'] ?? null);
    }
}
