<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\SerializedSuiteRepository;

readonly class GetSerializedSuiteReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $serializedSuite = $this->serializedSuiteRepository->find($jobId);
        if (null === $serializedSuite || $serializedSuite->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
