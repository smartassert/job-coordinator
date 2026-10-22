<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\MetaState;

interface MachineInterface
{
    /**
     * @return non-empty-string
     */
    public function getId(): string;

    /**
     * @return ?non-empty-string
     */
    public function getState(): ?string;

    /**
     * @return ?non-empty-string
     */
    public function getStateCategory(): ?string;

    /**
     * @return ?non-empty-string
     */
    public function getIpAddress(): ?string;

    public function getActionFailure(): ?ActionFailureInterface;

    public function getMetaState(): MetaState;

    public function isActive(): bool;

    public function isReady(): bool;
}
