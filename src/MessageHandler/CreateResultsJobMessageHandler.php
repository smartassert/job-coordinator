<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateResultsJobMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
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
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $jobId = $message->getJobId();

        if ($this->resultsJobRepository->has($jobId)) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $jobId);
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $jobId,
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
