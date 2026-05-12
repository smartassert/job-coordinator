<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;
use App\Repository\ResultsJobRepository;

readonly class CreateResultsJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        if (!RemoteRequestType::createForResultsJobCreation()->equals($message->getRemoteRequestType())) {
            return null;
        }

        if ($this->resultsJobRepository->has($message->getJobId())) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
