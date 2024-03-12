<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\ResultsJobCreatedEvent;
use App\Exception\ResultsJobCreationException;
use App\Message\CreateResultsJobMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
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
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $job->id);
            $this->eventDispatcher->dispatch(new ResultsJobCreatedEvent(
                $message->authenticationToken,
                $job->id,
                $resultsJob
            ));
        } catch (\Throwable $e) {
            throw new ResultsJobCreationException($job, $e);
        }
    }
}
