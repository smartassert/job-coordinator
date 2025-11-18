<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

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
