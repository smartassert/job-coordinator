<?php

declare(strict_types=1);

namespace App\Model\Machine;

/**
 * @phpstan-type MachineActionFailureContext array<string,null|int|string>
 */
interface ActionFailureInterface
{
    public function getAction(): string;

    public function getType(): string;

    /**
     * @return array<string, null|int|string>
     */
    public function getContext(): array;
}
