<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobComponentHandler\SerializedSuiteHandler;

readonly class CreateMachineReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteHandler $serializedSuiteJobComponentHandler,
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

        if ($this->serializedSuiteJobComponentHandler->hasFailed($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        $serializedSuite = $this->serializedSuiteRepository->get($jobId);
        if (null === $serializedSuite || !$serializedSuite->isPrepared()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if (!$this->resultsJobRepository->has($jobId)) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
