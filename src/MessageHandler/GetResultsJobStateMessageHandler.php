<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetResultsJobStateMessageHandler
{
    public function __construct(
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
        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if (null === $resultsJob) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        if (
            true === $this->jobPreparationInspector->hasFailed($message->getJobId())
            || $resultsJob->hasEndState()
        ) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $resultsJobState = $this->resultsClient->getJobStatus($message->authenticationToken, $message->getJobId());
            $this->eventDispatcher->dispatch(new ResultsJobStateRetrievedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $resultsJobState
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
