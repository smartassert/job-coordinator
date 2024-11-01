<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetResultsJobStateMessageHandler
{
    public function __construct(
        private JobRepository $jobRepository,
        private ResultsJobRepository $resultsJobRepository,
        private ResultsClient $resultsClient,
        private EventDispatcherInterface $eventDispatcher,
        private JobPreparationInspectorInterface $jobPreparationInspector,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(GetResultsJobStateMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        if (true === $this->jobPreparationInspector->hasFailed($job)) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob || $resultsJob->hasEndState()) {
            return;
        }

        try {
            $resultsJobState = $this->resultsClient->getJobStatus($message->authenticationToken, $job->id);
            $this->eventDispatcher->dispatch(new ResultsJobStateRetrievedEvent(
                $message->authenticationToken,
                $job->id,
                $resultsJobState
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
