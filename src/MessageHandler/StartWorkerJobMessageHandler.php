<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\MessageDispatcher\StartWorkerJobMessageDispatcher;
use App\Repository\JobRepository;
use App\Services\WorkerClientFactory;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class StartWorkerJobMessageHandler
{
    public function __construct(
        private readonly StartWorkerJobMessageDispatcher $messageDispatcher,
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly WorkerClientFactory $workerClientFactory,
    ) {
    }

    /**
     * @throws WorkerJobStartException
     */
    public function __invoke(StartWorkerJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if (null === $job) {
            return;
        }

        $jobSerializedSuiteState = $job->getSerializedSuiteState();

        if ('failed' === $jobSerializedSuiteState) {
            return;
        }

        if (in_array($jobSerializedSuiteState, ['requested', 'preparing/running', 'preparing/halted'])) {
            $this->messageDispatcher->dispatch($message);

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $serializedSuite = $this->serializedSuiteClient->read(
                $message->authenticationToken,
                $job->serializedSuiteId,
            );

            $workerClient->createJob(
                $job->id,
                $job->resultsToken,
                $job->maximumDurationInSeconds,
                $serializedSuite
            );
        } catch (\Throwable $e) {
            throw new WorkerJobStartException($job, $e);
        }
    }
}
