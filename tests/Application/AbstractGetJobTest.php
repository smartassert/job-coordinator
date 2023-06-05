<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\FileClient;
use SmartAssert\SourcesClient\SourceClient;
use SmartAssert\SourcesClient\SuiteClient;
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
        $apiToken = $apiTokenProvider->get('user@example.com');

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

    public function testGetSuccess(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

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

        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suite->getId(), 600);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $getResponse = self::$staticApplicationClient->makeGetJobRequest($apiToken, $jobId);

        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('application/json', $getResponse->getHeaderLine('content-type'));

        $job = $jobRepository->find($jobId);
        self::assertInstanceOf(Job::class, $job);

        $responseData = json_decode($getResponse->getBody()->getContents(), true);

        self::assertEquals($job->jsonSerialize(), $responseData);
    }
}
