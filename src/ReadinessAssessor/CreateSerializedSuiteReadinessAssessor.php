<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\SerializedSuiteRepository;

readonly class CreateSerializedSuiteReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForSerializedSuiteCreation()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if ($this->serializedSuiteRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
