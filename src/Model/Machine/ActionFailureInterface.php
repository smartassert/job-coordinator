<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-type SerializedMachineActionFailure array{
 *   action: non-empty-string,
 *   type: non-empty-string,
 *   context: null|array<mixed>
 * }
 * @phpstan-type MachineActionFailureContext array<string,null|int|string>
 */
interface ActionFailureInterface extends SerializeToArrayInterface
{
    /**
     * @return non-empty-string
     */
    public function getAction(): string;

    /**
     * @return non-empty-string
     */
    public function getType(): string;

    /**
     * @return MachineActionFailureContext
     */
    public function getContext(): array;

    /**
     * @return SerializedMachineActionFailure
     */
    public function toArray(): array;
}
