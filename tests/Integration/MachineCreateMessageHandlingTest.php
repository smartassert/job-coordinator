<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use SmartAssert\TestSourcesClient\FileClient;
use SmartAssert\TestSourcesClient\FileSourceClient;
use SmartAssert\TestSourcesClient\SuiteClient;
use Symfony\Component\Uid\Ulid;

class MachineCreateMessageHandlingTest extends AbstractCreateJobSuccessSetup
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

    public function testSuccessfulMachineCreateRequestExists(): void
    {
        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllRemoteRequests();

        $jobId = $this->getJob()?->getId();
        \assert(is_string($jobId));

        $this->waitUntilSuccessfulMachineCreateRemoteRequestExists($jobId);

        $successfulMachineCreateRequestCount = $remoteRequestRepository->count(
            [
                'jobId' => $jobId,
                'state' => RequestState::SUCCEEDED->value,
                'type' => new RemoteRequestType(
                    JobComponent::MACHINE,
                    RemoteRequestAction::CREATE,
                ),
            ]
        );

        self::assertSame(
            1,
            $successfulMachineCreateRequestCount,
            'Incorrect machine/create request count, should be only one.'
        );
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

    private function waitUntilSuccessfulMachineCreateRemoteRequestExists(string $jobId): void
    {
        $this->waitUntilMachineCreateRemoteRequestsExist($jobId, RequestState::SUCCEEDED->value);
    }

    private function waitUntilMachineCreateRemoteRequestsExist(string $jobId, ?string $state): void
    {
        $waitThreshold = self::MICROSECONDS_PER_SECOND * 30;
        $totalWaitTime = 0;
        $period = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $has = false;

        while (false === $has && $totalWaitTime < $waitThreshold) {
            $totalWaitTime += $period;
            usleep($period);
            $has = $this->hasMachineCreateRemoteRequests($jobId, $state);
        }

        if ($totalWaitTime >= $waitThreshold) {
            throw new \RuntimeException('Exceeded threshold waiting for machine create remote requests');
        }
    }

    private function hasMachineCreateRemoteRequests(string $jobId, ?string $state = null): bool
    {
        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $criteria = [
            'jobId' => $jobId,
            'type' => 'machine/create',
        ];

        if (is_string($state)) {
            $criteria['state'] = $state;
        }

        return $remoteRequestRepository->count($criteria) > 0;
    }
}
