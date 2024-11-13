<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\ResultsJob;
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

class ResultsJobRetrieveMessageHandlingForMissingResultsJobTest extends AbstractApplicationTest
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

    public function testAbortedResultsJobRetrieveRequestExists(): void
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

        $this->waitUntilResultsJobRetrieveRemoteRequestExists($jobId);

        $this->removeResultsJob($jobId);

        $entityManager->clear();
        $entityManager->flush();

        $this->waitUntilAbortedResultsJobRetrieveRemoteRequestExists($jobId);

        $abortedResultsJobRetrieveRequestCount = $remoteRequestRepository->count(
            [
                'jobId' => $job->getId(),
                'state' => RequestState::ABORTED->value,
                'type' => new RemoteRequestType(
                    JobComponent::RESULTS_JOB,
                    RemoteRequestAction::RETRIEVE,
                ),
            ]
        );

        self::assertSame(
            1,
            $abortedResultsJobRetrieveRequestCount,
            'No aborted results-job/retrieve request found.'
        );
    }

    private function waitUntilResultsJobRetrieveRemoteRequestExists(string $jobId): void
    {
        $this->waitUntilResultsJobRetrieveRemoteRequestsExist($jobId, null);
    }

    private function waitUntilAbortedResultsJobRetrieveRemoteRequestExists(string $jobId): void
    {
        $this->waitUntilResultsJobRetrieveRemoteRequestsExist($jobId, RequestState::ABORTED->value);
    }

    private function waitUntilResultsJobRetrieveRemoteRequestsExist(string $jobId, ?string $state): void
    {
        $waitThreshold = self::MICROSECONDS_PER_SECOND * 60;
        $totalWaitTime = 0;
        $period = (int) (self::MICROSECONDS_PER_SECOND * 0.1);

        $remoteRequests = [];

        while (0 === count($remoteRequests) && $totalWaitTime < $waitThreshold) {
            $totalWaitTime += $period;
            usleep($period);
            $remoteRequests = $this->getResultsJobRetrieveRemoteRequests($jobId, $state);
        }

        if ($totalWaitTime >= $waitThreshold) {
            throw new \RuntimeException('Exceeded threshold waiting for results job retrieve remote requests');
        }
    }

    /**
     * @return RemoteRequest[]
     */
    private function getResultsJobRetrieveRemoteRequests(
        string $jobId,
        ?string $state = null,
    ): array {
        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $criteria = [
            'jobId' => $jobId,
            'type' => 'results-job/retrieve',
        ];

        if (is_string($state)) {
            $criteria['state'] = $state;
        }

        return $remoteRequestRepository->findBy($criteria, ['index' => 'ASC']);
    }

    private function removeResultsJob(string $jobId): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(ResultsJob::class, 'r')
            ->where('r.jobId = :jobId')
            ->setParameter('jobId', $jobId)
        ;

        $query = $queryBuilder->getQuery();
        $query->execute();
    }
}
