<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Machine as MachineEntity;
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
        private MachineModelFactory $machineModelFactory,
    ) {}

    public function createForJob(JobInterface $job): Machine
    {
        $machineEntity = $this->machineRepository->find($job->getId());
        $machineModel = null;

        if ($machineEntity instanceof MachineEntity) {
            $machineModel = $this->machineModelFactory->createFromMachineEntity($machineEntity);
        }

        return new Machine(
            $machineModel,
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::MACHINE)
            ),
            $this->preparationFactory->create($job->getId()),
        );
    }
}
