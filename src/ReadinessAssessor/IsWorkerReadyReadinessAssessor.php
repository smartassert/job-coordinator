<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;

readonly class IsWorkerReadyReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $machine = $this->machineRepository->find($jobId);
        if (null === $machine) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if (true === $machine->getMetaState()->ended) {
            return MessageHandlingReadiness::NEVER;
        }

        if (true === $machine->getIsReady()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
