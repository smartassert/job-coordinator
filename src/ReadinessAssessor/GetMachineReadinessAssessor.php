<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        $machine = $this->machineRepository->find($message->getJobId());
        if (null === $machine) {
            return MessageHandlingReadiness::NEVER;
        }

        if ($machine->getMetaState()->ended) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
