<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\RequestState;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\ResultsJobCreationException;
use App\Message\CreateResultsJobMessage;
use App\Repository\JobRepository;
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
    ) {
    }

    /**
     * @throws ResultsJobCreationException
     */
    public function __invoke(CreateResultsJobMessage $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if (null === $job) {
            return;
        }

        $job->setResultsJobRequestState(RequestState::REQUESTING);

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $message->jobId);
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $message->jobId,
                $resultsJob
            ));
        } catch (\Throwable $e) {
            $job->setResultsJobRequestState(RequestState::HALTED);
            $this->jobRepository->add($job);

            throw new ResultsJobCreationException($job, $e);
        }
    }
}
