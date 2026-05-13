<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\ResultsJobRepository;

readonly class CreateResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        if ($this->resultsJobRepository->has($message->getJobId())) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
