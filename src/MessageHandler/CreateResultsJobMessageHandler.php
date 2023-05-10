<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ResultsJobCreationState;
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

        $job->setResultsJobCreationState(ResultsJobCreationState::REQUESTING);

        try {
            $resultsJob = $this->resultsClient->createJob($message->authenticationToken, $message->jobId);
            $job = $job->setResultsJobCreationState(null);
            $job = $job->setResultsToken($resultsJob->token);
            $this->jobRepository->add($job);
        } catch (\Throwable $e) {
            $job->setResultsJobCreationState(ResultsJobCreationState::HALTED);

            throw new ResultsJobCreationException($job, $e);
        }
    }
}
