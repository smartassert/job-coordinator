<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\SerializedSuiteStore;

readonly class CreateMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
        private SerializedSuiteStore $serializedSuiteStore,
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForMachineCreation()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if ($this->machineRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        $serializedSuite = $this->serializedSuiteStore->retrieve($jobId);
        if (null === $serializedSuite || !$serializedSuite->isPrepared()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if (!$this->resultsJobRepository->has($jobId)) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
