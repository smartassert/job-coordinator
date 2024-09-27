<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Request\CreateJobRequest;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('createBadMethodDataProvider')]
    public function testCreateBadMethod(string $method): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $response = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $this->suiteId, null, $method);

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public static function createBadMethodDataProvider(): array
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

    #[DataProvider('unauthorizedUserDataProvider')]
    public function testCreateUnauthorizedUser(?string $apiToken): void
    {
        $response = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $this->suiteId, null);

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

    public function testCreateBadRequest(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $response = self::$staticApplicationClient->makeCreateJobRequest(
            $apiToken,
            $this->suiteId,
            0
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame(
            [
                'class' => 'bad_request',
                'type' => 'wrong_size',
                'parameter' => [
                    'name' => 'maximum_duration_in_seconds',
                    'value' => 0,
                    'requirements' => [
                        'data_type' => 'integer',
                        'size' => [
                            'minimum' => 1,
                            'maximum' => CreateJobRequest::MAXIMUM_DURATION_IN_SECONDS_MAX_SIZE,
                        ],
                    ],
                ],
            ],
            json_decode($response->getBody()->getContents(), true)
        );
    }
}
