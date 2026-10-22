<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\MetaState;
use SmartAssert\WorkerManagerClient\Model\Machine as RemoteMachine;

final readonly class WorkerManagerMachine implements MachineInterface
{
    public function __construct(
        private RemoteMachine $workerManagerMachine,
    ) {}

    public function getId(): ?string
    {
        return $this->getNonEmptyString($this->workerManagerMachine->id);
    }

    public function getState(): ?string
    {
        return $this->getNonEmptyString($this->workerManagerMachine->state);
    }

    public function getStateCategory(): ?string
    {
        return $this->getNonEmptyString($this->workerManagerMachine->stateCategory);
    }

    public function getIpAddress(): ?string
    {
        $ipAddresses = $this->workerManagerMachine->ipAddresses;
        $firstIpAddress = $ipAddresses[0] ?? null;
        $firstIpAddress = is_string($firstIpAddress) ? $firstIpAddress : null;

        return $this->getNonEmptyString($firstIpAddress);
    }

    public function getActionFailure(): ?ActionFailureInterface
    {
        return null === $this->workerManagerMachine->actionFailure
            ? null
            : new WorkerManagerActionFailure($this->workerManagerMachine->actionFailure);
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(
            $this->workerManagerMachine->metaState->ended,
            $this->workerManagerMachine->metaState->succeeded,
            $this->workerManagerMachine->metaState->pending,
        );
    }

    public function isActive(): bool
    {
        return $this->workerManagerMachine->hasActiveState;
    }

    public function isReady(): false
    {
        return false;
    }

    /**
     * @return ?non-empty-string
     */
    private function getNonEmptyString(?string $value): ?string
    {
        return (null === $value || '' === $value) ? null : $value;
    }
}
