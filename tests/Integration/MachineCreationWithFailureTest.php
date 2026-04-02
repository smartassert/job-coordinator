<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use SmartAssert\TestSourcesClient\FileClient;
use SmartAssert\TestSourcesClient\FileSourceClient;
use SmartAssert\TestSourcesClient\SuiteClient;
use Symfony\Component\Uid\Ulid;

class MachineCreationWithFailureTest extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;

    private const int MICROSECONDS_PER_SECOND = 1000000;

    public static function tearDownAfterClass(): void
    {
        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllJobs();
        $entityRemover->removeAllRemoteRequests();
    }

    public function testMachineCreationResultsInFindFailure(): void
    {
        $jobId = $this->getJob()?->getId();
        \assert(is_string($jobId));

        $machineData = $this->waitUntilJobStateCategoryIs($jobId, 'pre_active');

        self::assertSame('pre_active', $machineData['state_category']);
        self::assertNull($machineData['ip_address']);
        self::assertNull($machineData['action_failure']);
        self::assertSame(
            [
                'ended' => false,
                'succeeded' => false,
            ],
            $machineData['meta_state'],
        );
        self::assertSame([], $machineData['preparation']);
        self::assertNotSame([], $machineData['requests']);

        $machineData = $this->waitUntilJobStateCategoryIs($jobId, 'end');

        self::assertSame('end', $machineData['state_category']);
        self::assertNull($machineData['ip_address']);
        self::assertSame(
            [
                'action' => 'find',
                'type' => 'vendor_authentication_failure',
                'context' => [
                    'provider' => null,
                ],
            ],
            $machineData['action_failure'],
        );
        self::assertSame(
            [
                'ended' => true,
                'succeeded' => false,
            ],
            $machineData['meta_state'],
        );
        self::assertSame([], $machineData['preparation']);
        self::assertNotSame([], $machineData['requests']);
    }

    protected static function createSuiteId(): string
    {
        $fileSourceClient = self::getContainer()->get(FileSourceClient::class);
        \assert($fileSourceClient instanceof FileSourceClient);

        $fileSourceLabel = (string) new Ulid();

        $fileSourceId = $fileSourceClient->create(self::$apiToken, $fileSourceLabel);
        \assert(is_string($fileSourceId));

        $fileClient = self::getContainer()->get(FileClient::class);
        \assert($fileClient instanceof FileClient);

        $fileClient->add(self::$apiToken, $fileSourceId, 'test.yaml', '- test file content');

        $suiteClient = self::getContainer()->get(SuiteClient::class);
        \assert($suiteClient instanceof SuiteClient);

        $suiteLabel = (string) new Ulid();

        $suiteId = $suiteClient->create(self::$apiToken, $fileSourceId, $suiteLabel, ['test.yaml']);
        \assert(is_string($suiteId));

        return $suiteId;
    }

    /**
     * @return array<mixed>
     */
    private function waitUntilJobStateCategoryIs(string $jobId, string $stateCategory): array
    {
        $waitThreshold = self::MICROSECONDS_PER_SECOND * 90;
        $totalWaitTime = 0;
        $period = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $has = false;

        while (false === $has && $totalWaitTime < $waitThreshold) {
            $totalWaitTime += $period;
            usleep($period);

            $machineData = $this->getMachineData($jobId);

            $has = is_array($machineData) && $machineData['state_category'] === $stateCategory;
        }

        if ($totalWaitTime >= $waitThreshold) {
            throw new \RuntimeException(
                'Exceeded threshold waiting for machine state category to be "' . $stateCategory . '".'
            );
        }

        \assert(is_array($machineData));

        return $machineData;
    }

    /**
     * @return null|array<mixed>
     */
    private function getMachineData(string $jobId): ?array
    {
        $getJobResponse = self::$staticApplicationClient->makeGetJobRequest(
            self::$apiToken,
            $jobId
        );

        $jobData = json_decode($getJobResponse->getBody()->getContents(), true);
        \assert(is_array($jobData));

        $componentsData = $jobData['components'] ?? [];
        \assert(is_array($componentsData));

        $machineData = $componentsData['machine'];
        \assert(null === $machineData || is_array($machineData));

        return $machineData;
    }
}
