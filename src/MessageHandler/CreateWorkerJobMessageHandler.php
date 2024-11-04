<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\NotReadyToCreateWorkerJobEvent;
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
     */
    public function __invoke(CreateWorkerJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuiteEntity) {
            $this->eventDispatcher->dispatch(new NotReadyToCreateWorkerJobEvent($message));

            return;
        }

        $serializedSuiteState = $serializedSuiteEntity->getState();
        if ('failed' === $serializedSuiteState) {
            return;
        }

        if (in_array($serializedSuiteState, ['requested', 'preparing/running', 'preparing/halted'])) {
            $this->eventDispatcher->dispatch(new NotReadyToCreateWorkerJobEvent($message));

            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            $this->eventDispatcher->dispatch(new NotReadyToCreateWorkerJobEvent($message));

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

            $this->eventDispatcher->dispatch(new CreateWorkerJobRequestedEvent(
                $job->id,
                $message->machineIpAddress,
                $workerJob,
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}