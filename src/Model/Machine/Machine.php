<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\MetaState;

final readonly class Machine
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
        private ?ActionFailure $actionFailure,
        private MetaState $metaState,
        private bool $isActive,
        private bool $isReady,
        private bool $isEnding,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return non-empty-string
     */
    public function getStateCategory(): string
    {
        return $this->stateCategory;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getActionFailure(): ?ActionFailure
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
