<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Machine;
use App\Model\JobInterface;
use App\Model\RemoteRequestCollection;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\JobComponentPreparationFactory\MachineFactory;

readonly class MachineComponentFactory
{
    public function __construct(
        private MachineRepository $machineRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private MachineFactory $preparationFactory,
    ) {}

    public function createForJob(JobInterface $job): Machine
    {
        return new Machine(
            $this->machineRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::MACHINE)
            ),
            $this->preparationFactory->create($job->getId()),
        );
    }
}
