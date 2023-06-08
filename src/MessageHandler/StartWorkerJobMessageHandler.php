<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\WorkerJobStartRequestedEvent;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\Repository\JobRepository;
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

        $jobSerializedSuiteState = $job->getSerializedSuiteState();

        if ('failed' === $jobSerializedSuiteState) {
            return;
        }

        $resultsToken = $job->getResultsToken();
        $serializedSuiteId = $job->getSerializedSuiteId();
        if (
            null === $resultsToken
            || null === $serializedSuiteId
            || in_array($jobSerializedSuiteState, ['requested', 'preparing/running', 'preparing/halted'])
        ) {
            $this->messageBus->dispatch($message);

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read($message->authenticationToken, $serializedSuiteId);

            $workerJob = $workerClient->createJob(
                $job->id,
                $resultsToken,
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
