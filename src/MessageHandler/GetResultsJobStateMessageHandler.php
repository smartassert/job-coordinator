<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\ResultsJobStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\Repository\JobRepository;
use App\Services\JobPreparationInspectorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetResultsJobStateMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsClient $resultsClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JobPreparationInspectorInterface $jobPreparationInspector,
    ) {
    }

    /**
     * @throws ResultsJobStateRetrievalException
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

        try {
            $resultsJobState = $this->resultsClient->getJobStatus($message->authenticationToken, $job->id);
            $this->eventDispatcher->dispatch(new ResultsJobStateRetrievedEvent(
                $message->authenticationToken,
                $job->id,
                $resultsJobState
            ));
        } catch (\Throwable $e) {
            throw new ResultsJobStateRetrievalException($job, $e, $message);
        }
    }
}
