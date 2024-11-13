<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateWorkerJobMessageHandler
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
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $jobId = $message->getJobId();

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($jobId);
        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (
            null === $serializedSuiteEntity
            || $serializedSuiteEntity->isPreparing()
            || null === $resultsJob
        ) {
            $this->eventDispatcher->dispatch(new MessageNotYetHandleableEvent($message));

            return;
        }

        $serializedSuiteId = $serializedSuiteEntity->getId();

        if (null === $serializedSuiteId || $serializedSuiteEntity->hasFailed()) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read($message->authenticationToken, $serializedSuiteId);

            $workerJob = $workerClient->createJob(
                $jobId,
                $resultsJob->token,
                $job->getMaximumDurationInSeconds(),
                $serializedSuite
            );

            $this->eventDispatcher->dispatch(new CreateWorkerJobRequestedEvent(
                $jobId,
                $message->machineIpAddress,
                $workerJob,
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
