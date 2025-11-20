<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\ResultsJobRepository;

readonly class CreateResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForResultsJobCreation()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if ($this->resultsJobRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
