<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use App\Tests\Services\AuthenticationConfiguration;
use SmartAssert\SourcesClient\FileClient;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
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

    public function testCreateSuccess(): void
    {
        $apiToken = self::$authenticationConfiguration->getValidApiToken();

        $sourceClient = self::getContainer()->get(SourceClient::class);
        \assert($sourceClient instanceof SourceClient);
        $source = $sourceClient->createFileSource($apiToken, md5((string) rand()));

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);
        $fileClient->add($apiToken, $source->getId(), 'test1.yaml', 'test 1 contents');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);
        $suite = $suiteClient->create($apiToken, $source->getId(), md5((string) rand()), ['test1.yaml']);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(0, $jobRepository->findAll());

        $response = $this->applicationClient->makeCreateJobRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $suite->getId(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        self::assertArrayHasKey('job', $responseData);
        $jobData = $responseData['job'];

        self::assertArrayHasKey('id', $jobData);
        self::assertTrue(Ulid::isValid($jobData['id']));

        self::assertArrayHasKey('suite_id', $jobData);
        self::assertSame($suite->getId(), $jobData['suite_id']);

        self::assertArrayHasKey('serialized_suite_id', $jobData);
        $serializedSuiteId = $jobData['serialized_suite_id'];

        self::assertArrayHasKey('machine', $responseData);
        $machineData = $responseData['machine'];

        self::assertArrayHasKey('id', $machineData);
        self::assertSame($jobData['id'], $machineData['id']);

        self::assertArrayHasKey('state', $machineData);
        self::assertSame('create/received', $machineData['state']);

        self::assertArrayHasKey('ip_addresses', $machineData);
        self::assertSame([], $machineData['ip_addresses']);

        $jobs = $jobRepository->findAll();
        self::assertCount(1, $jobs);

        $job = $jobs[0];
        self::assertInstanceOf(Job::class, $job);
        self::assertSame($job->userId, self::$authenticationConfiguration->getUser()->id);
        self::assertSame($job->suiteId, $suite->getId());
        self::assertNotNull($job->resultsToken);

        $serializedSuiteClient = self::getContainer()->get(SerializedSuiteClient::class);
        \assert($serializedSuiteClient instanceof SerializedSuiteClient);

        $serializedSuite = $serializedSuiteClient->get($apiToken, $serializedSuiteId);

        self::assertSame($serializedSuiteId, $serializedSuite->getId());
        self::assertSame($suite->getId(), $serializedSuite->getSuiteId());
    }
}
