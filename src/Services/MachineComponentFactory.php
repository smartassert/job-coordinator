<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Machine;
use App\Model\JobComponent\Preparation;
use App\Model\JobInterface;
use App\Model\RemoteRequestCollection;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\JobComponentHandler\MachineHandler;

readonly class MachineComponentFactory
{
    public function __construct(
        private MachineRepository $machineRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private MachineHandler $handler,
    ) {}

    public function createForJob(JobInterface $job): Machine
    {
        $componentPreparation = $this->handler->getComponentPreparation($job->getId());
        $requestState = $this->handler->getRequestState($job->getId());

        return new Machine(
            $this->machineRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::MACHINE)
            ),
            new Preparation($componentPreparation, $requestState),
        );
    }
}
