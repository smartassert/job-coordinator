<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Model\JobInterface;
use App\Services\JobStore;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Services\EntityRemover;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class GetJobSuccessTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    public static function tearDownAfterClass(): void
    {
        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllJobs();
        $entityRemover->removeAllRemoteRequests();
    }

    public function testGetSuccess(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $suiteId = (string) new Ulid();

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);

        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $job = $jobStore->retrieve($jobId);
        self::assertInstanceOf(JobInterface::class, $job);

        $getResponse = self::$staticApplicationClient->makeGetJobRequest($apiToken, $jobId);

        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('application/json', $getResponse->getHeaderLine('content-type'));

        $responseData = json_decode($getResponse->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        self::assertSame($job->getId(), $responseData['id']);
        self::assertSame($job->getSuiteId(), $responseData['suite_id']);
        self::assertSame($job->getMaximumDurationInSeconds(), $responseData['maximum_duration_in_seconds']);
    }

    public function testGetForInvalidSerializedSuiteId(): void
    {
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $suiteId = (string) new Ulid();

        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);
        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $jobData = $this->getJobData($apiToken, $jobId);
        $threshold = 60;
        $count = 0;

        while (
            null === ($jobData['preparation']['failures']['serialized-suite'] ?? null)
            && $count < $threshold
        ) {
            sleep(1);
            $jobData = $this->getJobData($apiToken, $jobId);
            ++$count;
        }

        if ($count >= $threshold) {
            self::fail('Tried ' . $count . ' times to get expected failed serialized suite state.');
        }

        self::assertSame('failed', $jobData['preparation']['state']);
        self::assertSame(
            [
                'serialized-suite' => [
                    'type' => 'http',
                    'code' => 403,
                    'message' => 'Forbidden',
                ],
            ],
            $jobData['preparation']['failures'],
        );
    }

    /**
     * @return array{
     *     'preparation': array{
     *       'state': string,
     *       'failures': ?array<mixed>
     *     }
     * }
     */
    private function getJobData(string $apiToken, string $jobId): array
    {
        $getResponse = self::$staticApplicationClient->makeGetJobRequest($apiToken, $jobId);
        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('application/json', $getResponse->getHeaderLine('content-type'));

        ini_set('xdebug.var_display_max_depth', '6');

        $responseData = json_decode($getResponse->getBody()->getContents(), true);

        \assert(is_array($responseData));
        \assert(is_array($responseData['preparation']));
        \assert(is_string($responseData['preparation']['state']));
        \assert(is_array($responseData['preparation']['failures']));

        return $responseData;
    }
}
