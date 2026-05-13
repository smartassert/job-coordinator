<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\SerializedSuiteRepository;

readonly class CreateSerializedSuiteReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        if ($this->serializedSuiteRepository->has($message->getJobId())) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
