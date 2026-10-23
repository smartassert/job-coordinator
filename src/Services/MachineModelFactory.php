<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Machine\ActionFailure;
use App\Model\Machine\Machine;
use App\Model\MetaState;
use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerClientMachine;

final readonly class MachineModelFactory
{
    public function createFromWorkerManagerClientMachine(
        WorkerManagerClientMachine $clientMachine,
    ): Machine {
        $actionFailure = null;
        $clientMachineActionFailure = $clientMachine->actionFailure;
        if (null !== $clientMachineActionFailure) {
            $actionFailure = new ActionFailure(
                $clientMachineActionFailure->action,
                $clientMachineActionFailure->type,
                $clientMachineActionFailure->context,
            );
        }

        return new Machine(
            $clientMachine->id,
            $clientMachine->state,
            $clientMachine->stateCategory,
            $clientMachine->ipAddresses[0] ?? null,
            $actionFailure,
            new MetaState(
                $clientMachine->metaState->ended,
                $clientMachine->metaState->succeeded,
                $clientMachine->metaState->pending,
            ),
            $clientMachine->hasActiveState,
            false,
            $clientMachine->hasEndingState,
        );
    }

    public function createFromMachineEntity(\App\Entity\Machine $entity): Machine
    {
        $actionFailure = null;
        $entityActionFailure = $entity->getActionFailure();
        if (null !== $entityActionFailure) {
            $actionFailure = new ActionFailure(
                $entityActionFailure->getAction(),
                $entityActionFailure->getType(),
                $entityActionFailure->getContext(),
            );
        }

        $ipAddress = $entity->getIpAddress();
        $ipAddress = '' === $ipAddress ? null : $ipAddress;

        return new Machine(
            $entity->getId(),
            $entity->getState(),
            $entity->getStateCategory(),
            $ipAddress,
            $actionFailure,
            new MetaState(
                $entity->getMetaState()->ended,
                $entity->getMetaState()->succeeded,
                $entity->getMetaState()->pending,
            ),
            $entity->isActive(),
            $entity->isReady(),
            $entity->isEnding(),
        );
    }
}
