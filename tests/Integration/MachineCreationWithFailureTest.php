<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use webignition\WaitFor\WaitFor;

class MachineCreationWithFailureTest extends AbstractCreateJobSuccessSetup
{
    use GetClientAdapterTrait;
    use CreateSuiteIdTrait;

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

        $this->waitUntilJobStateCategoryIs($jobId, 'pre_active');
        $machineData = $this->getMachineData($jobId);

        self::assertSame('pre_active', $machineData['state_category']);
        self::assertNull($machineData['ip_address']);
        self::assertNull($machineData['action_failure']);
        self::assertSame(
            [
                'ended' => false,
                'succeeded' => false,
                'pending' => true,
            ],
            $machineData['meta_state'],
        );
        self::assertSame(
            [
                'state' => 'succeeded',
                'request_state' => 'succeeded',
            ],
            $machineData['preparation']
        );
        self::assertNotSame([], $machineData['requests']);

        $this->waitUntilJobStateCategoryIs($jobId, 'end');
        $machineData = $this->getMachineData($jobId);

        self::assertSame('end', $machineData['state_category']);
        self::assertNull($machineData['ip_address']);
        self::assertSame(
            [
                'action' => 'find',
                'type' => 'vendor_authentication_failure',
                'context' => [
                    'provider' => 'digitalocean',
                ],
            ],
            $machineData['action_failure'],
        );
        self::assertSame(
            [
                'ended' => true,
                'succeeded' => false,
                'pending' => false,
            ],
            $machineData['meta_state'],
        );
        self::assertSame(
            [
                'state' => 'succeeded',
                'request_state' => 'succeeded',
            ],
            $machineData['preparation']
        );
        self::assertNotSame([], $machineData['requests']);
    }

    /**
     * @return array<mixed>
     */
    private function waitUntilJobStateCategoryIs(string $jobId, string $stateCategory): void
    {
        new WaitFor()->waitFor(
            90,
            function () use ($jobId, $stateCategory) {
                $machineData = $this->getMachineData($jobId);

                return is_array($machineData) && $machineData['state_category'] === $stateCategory;
            },
        );
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
