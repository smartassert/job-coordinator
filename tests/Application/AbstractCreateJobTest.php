<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
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
            'GET' => [
                'method' => 'GET',
            ],
            'HEAD' => [
                'method' => 'HEAD',
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
    public function testCreateUnauthorizedUser(callable $userTokenCreator): void
    {
        $response = $this->applicationClient->makeCreateJobRequest(
            $userTokenCreator(self::$authenticationConfiguration),
            $this->suiteId
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

    public function testCreateSuccess(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        $response = $this->applicationClient->makeCreateJobRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $this->suiteId
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        self::assertArrayHasKey('suite_id', $responseData);
        self::assertSame($this->suiteId, $responseData['suite_id']);

        self::assertArrayHasKey('label', $responseData);
        self::assertTrue(Ulid::isValid($responseData['label']));

        $jobs = $jobRepository->findAll();
        self::assertCount(1, $jobs);

        $job = $jobs[0];
        self::assertInstanceOf(Job::class, $job);
        self::assertSame($job->getUserId(), self::$authenticationConfiguration->getUser()->id);
        self::assertSame($job->getSuiteId(), $this->suiteId);
        self::assertNotNull($job->getResultsToken());
    }
}
