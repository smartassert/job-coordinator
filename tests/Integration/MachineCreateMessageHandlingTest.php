<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Job;
use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Application\AbstractApplicationTest;
use App\Tests\Services\EntityRemover;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class MachineCreateMessageHandlingTest extends AbstractApplicationTest
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
        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user1@example.com');

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);

        $entityRemover->removeAllRemoteRequests();

        $createResponse = self::$staticApplicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        self::assertIsArray($createResponseData);
        self::assertArrayHasKey('id', $createResponseData);
        self::assertTrue(Ulid::isValid($createResponseData['id']));
        $jobId = $createResponseData['id'];

        $job = $jobRepository->find($jobId);
        self::assertInstanceOf(Job::class, $job);

        $this->waitUntilSuccessfulMachineCreateRemoteRequestExists($jobId);

        $successfulMachineCreateRequestCount = $remoteRequestRepository->count(
            [
                'jobId' => $job->id,
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
