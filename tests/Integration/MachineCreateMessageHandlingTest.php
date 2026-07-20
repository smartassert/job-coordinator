<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Tests\Application\AbstractCreateJobSuccessSetup;
use App\Tests\Services\EntityRemover;
use webignition\WaitFor\WaitFor;

class MachineCreateMessageHandlingTest extends AbstractCreateJobSuccessSetup
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

    public function testSuccessfulMachineCreateRequestExists(): void
    {
        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllRemoteRequests();

        $jobId = $this->getJob()?->getId();
        \assert(is_string($jobId));

        $this->waitUntilMachineCreateRemoteRequestsExist($jobId, RequestState::SUCCEEDED->value);

        $successfulMachineCreateRequestCount = $remoteRequestRepository->count(
            [
                'jobId' => $jobId,
                'state' => RequestState::SUCCEEDED->value,
                'type' => RemoteRequestType::createForMachineCreation(),
            ]
        );

        self::assertSame(
            1,
            $successfulMachineCreateRequestCount,
            'Incorrect machine/create request count, should be only one.'
        );
    }

    private function waitUntilMachineCreateRemoteRequestsExist(string $jobId, ?string $state): void
    {
        new WaitFor()->waitFor(
            30,
            function () use ($jobId, $state) {
                return $this->hasMachineCreateRemoteRequests($jobId, $state);
            },
        );
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
