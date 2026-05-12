<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::MACHINE !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::RETRIEVE !== $requestType->action) {
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
