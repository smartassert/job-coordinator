<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\WorkerJobStartRequestedEvent;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class StartWorkerJobMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly WorkerClientFactory $workerClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws WorkerJobStartException
     */
    public function __invoke(StartWorkerJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuiteEntity) {
            $this->messageBus->dispatch($message);

            return;
        }

        $serializedSuiteState = $serializedSuiteEntity->getState();
        if ('failed' === $serializedSuiteState) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (
            null === $resultsJob
            || in_array($serializedSuiteState, ['requested', 'preparing/running', 'preparing/halted'])
        ) {
            $this->messageBus->dispatch($message);

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
                $message->authenticationToken,
                $job->id,
                $workerJob
            ));
        } catch (\Throwable $e) {
            throw new WorkerJobStartException($job, $e);
        }
    }
}
