<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\RequestState;
use App\Exception\ResultsJobCreationException;
use App\Message\CreateResultsJobMessage;
use App\Repository\JobRepository;
use SmartAssert\ResultsClient\Client as ResultsClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateResultsJobMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsClient $resultsClient,
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
            $job = $job->setResultsJobRequestState(null);
            $job = $job->setResultsToken($resultsJob->token);
            $this->jobRepository->add($job);
        } catch (\Throwable $e) {
            $job->setResultsJobRequestState(RequestState::HALTED);
            $this->jobRepository->add($job);

            throw new ResultsJobCreationException($job, $e);
        }
    }
}
