<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\MetaState;

final readonly class Machine implements MachineInterface
{
    /**
     * @param non-empty-string  $id
     * @param non-empty-string  $state
     * @param non-empty-string  $stateCategory
     * @param ?non-empty-string $ipAddress
     */
    public function __construct(
        private string $id,
        private string $state,
        private string $stateCategory,
        private ?string $ipAddress,
        private ?SerializableActionFailureInterface $actionFailure,
        private MetaState $metaState,
        private bool $isActive,
        private bool $isReady,
        private bool $isEnding,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getStateCategory(): string
    {
        return $this->stateCategory;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getActionFailure(): ?SerializableActionFailureInterface
    {
        return $this->actionFailure;
    }

    public function getMetaState(): MetaState
    {
        return $this->metaState;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isReady(): bool
    {
        return $this->isReady;
    }

    public function isEnding(): bool
    {
        return $this->isEnding;
    }
}
