<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\MessageHandlerTargetEntityNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetResultsJobStateMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
        private ResultsJobRepository $resultsJobRepository,
        private ResultsClient $resultsClient,
        private EventDispatcherInterface $eventDispatcher,
        private JobPreparationInspectorInterface $jobPreparationInspector,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     * @throws MessageHandlerTargetEntityNotFoundException
     */
    public function __invoke(GetResultsJobStateMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $jobId = $message->getJobId();

        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob) {
            throw new MessageHandlerTargetEntityNotFoundException($message, 'ResultsJob');
        }

        if (
            true === $this->jobPreparationInspector->hasFailed($job)
            || $resultsJob->hasEndState()
        ) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $resultsJobState = $this->resultsClient->getJobStatus($message->authenticationToken, $jobId);
            $this->eventDispatcher->dispatch(new ResultsJobStateRetrievedEvent(
                $message->authenticationToken,
                $jobId,
                $resultsJobState
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
