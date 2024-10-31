<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\NotReadyToStartWorkerJobEvent;
use App\Event\WorkerJobStartRequestedEvent;
use App\Exception\WorkerJobCreationException;
use App\Message\StartWorkerJobMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class StartWorkerJobMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly WorkerClientFactory $workerClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws WorkerJobCreationException
     */
    public function __invoke(StartWorkerJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuiteEntity) {
            $this->eventDispatcher->dispatch(new NotReadyToStartWorkerJobEvent($message));

            return;
        }

        $serializedSuiteState = $serializedSuiteEntity->getState();
        if ('failed' === $serializedSuiteState) {
            return;
        }

        if (in_array($serializedSuiteState, ['requested', 'preparing/running', 'preparing/halted'])) {
            $this->eventDispatcher->dispatch(new NotReadyToStartWorkerJobEvent($message));

            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            $this->eventDispatcher->dispatch(new NotReadyToStartWorkerJobEvent($message));

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read(
                $message->authenticationToken,
                $serializedSuiteEntity->getId()
            );

            $workerJob = $workerClient->createJob(
                $job->id,
                $resultsJob->token,
                $job->maximumDurationInSeconds,
                $serializedSuite
            );

            $this->eventDispatcher->dispatch(new WorkerJobStartRequestedEvent(
                $job->id,
                $message->machineIpAddress,
                $workerJob,
            ));
        } catch (\Throwable $e) {
            throw new WorkerJobCreationException($job, $e, $message);
        }
    }
}
