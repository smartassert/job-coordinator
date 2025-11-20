<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForMachineRetrieval()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $machine = $this->machineRepository->find($jobId);
        if (null === $machine) {
            return MessageHandlingReadiness::NEVER;
        }

        if ($machine->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
