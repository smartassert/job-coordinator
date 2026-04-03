<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Model\JobInterface;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerJobCreationFailureRepository;
use App\Services\JobStore;
use App\Tests\Model\StagingConfiguration;
use App\Tests\Model\StagingOutput;
use App\Tests\Services\ApplicationClient\Client;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

readonly class Stager
{
    public function __construct(
        private ApiTokenProvider $apiTokenProvider,
        private JobStore $jobStore,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private MachineRepository $machineRepository,
        private EntityRemover $entityRemover,
        private RemoteRequestRepository $remoteRequestRepository,
        private ResultsJobRepository $resultsJobRepository,
        private WorkerComponentStateRepository $workerComponentStateRepository,
        private WorkerJobCreationFailureRepository $workerJobCreationFailureRepository,
    ) {}

    public function stage(Client $applicationClient, StagingConfiguration $configuration): StagingOutput
    {
        $apiToken = $this->apiTokenProvider->get('user1@example.com');

        $job = $this->createJob($applicationClient, $apiToken);
        $serializedSuite = $configuration->getSerializedSuiteCreator()($job->getId(), $this->serializedSuiteRepository);

        $machine = $configuration->getMachineCreator()($job->getId(), $this->machineRepository);
        $resultsJob = $configuration->getResultsJobCreator()($job->getId(), $this->resultsJobRepository);
        $configuration->getWorkerComponentStatesCreator()($job->getId(), $this->workerComponentStateRepository);
        $configuration->getWorkerJobCreationFailureCreator()($job->getId(), $this->workerJobCreationFailureRepository);

        $this->entityRemover->removeAllRemoteRequests();
        $this->entityRemover->removeAllRemoteRequestFailures();

        $configuration->getRemoteRequestsCreator()($job->getId(), $this->remoteRequestRepository);

        return new StagingOutput($apiToken, $job, $serializedSuite, $machine, $resultsJob);
    }

    /**
     * @param non-empty-string $apiToken
     */
    private function createJob(Client $applicationClient, string $apiToken): JobInterface
    {
        $suiteId = (string) new Ulid();
        $createResponse = $applicationClient->makeCreateJobRequest($apiToken, $suiteId, 600);

        $createResponseStatusCode = $createResponse->getStatusCode();
        if (200 !== $createResponseStatusCode) {
            throw new \RuntimeException(sprintf(
                'Failed to create job: expected status code "200", got "%d"',
                $createResponseStatusCode
            ));
        }

        $createResponseContentType = $createResponse->getHeaderLine('content-type');
        if ('application/json' !== $createResponseContentType) {
            throw new \RuntimeException(sprintf(
                'Failed to create job: expected content type "application/json", got "%s"',
                $createResponseContentType
            ));
        }

        $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
        if (!is_array($createResponseData)) {
            throw new \RuntimeException(sprintf(
                'Failed to create job: invalid response data; expected "array", got "%s"',
                gettype($createResponseData)
            ));
        }

        $jobId = $createResponseData['id'] ?? '';
        $jobId = is_string($jobId) ? $jobId : '';
        if (!Ulid::isValid($jobId)) {
            throw new \RuntimeException(sprintf(
                'Failed to create job: invalid job id; expected valid ULID, got "%s"',
                '' === $jobId ? '<empty>' : $jobId
            ));
        }

        $job = $this->jobStore->retrieve($jobId);
        if (null === $job) {
            throw new \RuntimeException(sprintf(
                'Failed to create job: job not found in job store; expected to find job with id "%s"',
                $jobId,
            ));
        }

        return $job;
    }
}
