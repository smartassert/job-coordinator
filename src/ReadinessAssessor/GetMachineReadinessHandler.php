<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        if (!RemoteRequestType::createForMachineRetrieval()->equals($message->getRemoteRequestType())) {
            return null;
        }

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
