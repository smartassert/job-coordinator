<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Application\AbstractApplicationTest;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class MachineCreateMessageHandlingForDeletedJobTest extends AbstractApplicationTest
{
    use GetClientAdapterTrait;

    private const int MICROSECONDS_PER_SECOND = 1000000;

    public function testAbortedMachineCreateRequestExists(): void
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

        foreach ($remoteRequestRepository->findAll() as $remoteRequest) {
            $entityManager->remove($remoteRequest);
            $entityManager->flush();
        }

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

        $this->waitUntilMachineCreateRemoteRequestExists($jobId);

        $this->removeJob($jobId);

        $entityManager->clear();
        $entityManager->flush();

        $this->waitUntilAbortedMachineCreateRemoteRequestExists($jobId);

        $abortedMachineCreateRequestCount = $remoteRequestRepository->count(
            [
                'jobId' => $job->id,
                'state' => RequestState::ABORTED->value,
                'type' => new RemoteRequestType(
                    JobComponent::MACHINE,
                    RemoteRequestAction::CREATE,
                ),
            ]
        );

        self::assertSame(
            1,
            $abortedMachineCreateRequestCount,
            'No aborted machine/create request found.'
        );
    }

    private function waitUntilMachineCreateRemoteRequestExists(string $jobId): void
    {
        $this->waitUntilMachineCreateRemoteRequestsExist($jobId, null);
    }

    private function waitUntilAbortedMachineCreateRemoteRequestExists(string $jobId): void
    {
        $this->waitUntilMachineCreateRemoteRequestsExist($jobId, RequestState::ABORTED->value);
    }

    private function waitUntilMachineCreateRemoteRequestsExist(string $jobId, ?string $state): void
    {
        $waitThreshold = self::MICROSECONDS_PER_SECOND * 30;
        $totalWaitTime = 0;
        $period = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $remoteRequests = [];

        while (0 === count($remoteRequests) && $totalWaitTime < $waitThreshold) {
            $totalWaitTime += $period;
            usleep($period);
            $remoteRequests = $this->getMachineCreateRemoteRequests($jobId, $state);
        }

        if ($totalWaitTime >= $waitThreshold) {
            throw new \RuntimeException('Exceeded threshold waiting for machine create remote requests');
        }
    }

    /**
     * @return RemoteRequest[]
     */
    private function getMachineCreateRemoteRequests(
        string $jobId,
        ?string $state = null,
    ): array {
        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $criteria = [
            'jobId' => $jobId,
            'type' => 'machine/create',
        ];

        if (is_string($state)) {
            $criteria['state'] = $state;
        }

        return $remoteRequestRepository->findBy($criteria, ['index' => 'ASC']);
    }

    private function removeJob(string $jobId): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(Job::class, 'j')
            ->where('j.id = :id')
            ->setParameter('id', $jobId)
        ;

        $query = $queryBuilder->getQuery();
        $query->execute();
    }
}
