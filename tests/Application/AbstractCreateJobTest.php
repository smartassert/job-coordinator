<?php

declare(strict_types=1);

namespace App\Tests\Application;

use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

abstract class AbstractCreateJobTest extends AbstractApplicationTest
{
    private string $suiteId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suiteId = (string) new Ulid();
    }

    /**
     * @dataProvider createBadMethodDataProvider
     */
    public function testCreateBadMethod(string $method): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $response = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $this->suiteId, $method);

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public function createBadMethodDataProvider(): array
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
    public function testCreateUnauthorizedUser(?string $apiToken): void
    {
        $response = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $this->suiteId);

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
}
