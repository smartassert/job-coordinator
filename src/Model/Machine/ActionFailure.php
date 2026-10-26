<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-type MachineActionFailureContext array<string,null|int|string>
 * @phpstan-type SerializedMachineActionFailure array{
 *   action: non-empty-string,
 *   type: non-empty-string,
 *   context: MachineActionFailureContext
 * }
 */
final readonly class ActionFailure implements SerializeToArrayInterface
{
    /**
     * @param non-empty-string            $action
     * @param non-empty-string            $type
     * @param MachineActionFailureContext $context
     */
    public function __construct(
        private string $action,
        private string $type,
        private array $context,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return non-empty-string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return MachineActionFailureContext
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return SerializedMachineActionFailure
     */
    public function toArray(): array
    {
        return [
            'action' => $this->getAction(),
            'type' => $this->getType(),
            'context' => $this->context,
        ];
    }
}
