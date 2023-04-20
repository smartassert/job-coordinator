<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Services\AuthenticationConfiguration;
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
        $response = $this->applicationClient->makeCreateJobRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $this->suiteId,
            $method
        );

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
    public function testCreateUnauthorizedUser(callable $userTokenCreator): void
    {
        $response = $this->applicationClient->makeCreateJobRequest(
            $userTokenCreator(self::$authenticationConfiguration),
            $this->suiteId,
        );

        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array<mixed>
     */
    public function unauthorizedUserDataProvider(): array
    {
        return [
            'no user token' => [
                'userTokenCreator' => function () {
                    return null;
                },
            ],
            'empty user token' => [
                'userTokenCreator' => function () {
                    return '';
                },
            ],
            'non-empty invalid user token' => [
                'userTokenCreator' => function (AuthenticationConfiguration $authenticationConfiguration) {
                    return $authenticationConfiguration->getInvalidApiToken();
                },
            ],
        ];
    }
}
