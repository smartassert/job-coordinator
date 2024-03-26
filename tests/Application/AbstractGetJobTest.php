<?php

declare(strict_types=1);

namespace App\Tests\Application;

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
        $apiToken = $apiTokenProvider->get('user1@example.com');

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

    public function testGetJobNotFound(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $response = self::$staticApplicationClient->makeGetJobRequest($apiToken, $suiteId);

        echo $response->getBody()->getContents();

        self::assertSame(401, $response->getStatusCode());
    }
}
