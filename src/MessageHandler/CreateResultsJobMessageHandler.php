<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\Repository\ResultsJobRepository;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateResultsJobMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly ResultsClient $resultsClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        if ($this->resultsJobRepository->has($job->getId())) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $job->getId());
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $job->getId(),
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
