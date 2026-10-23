<?php

declare(strict_types=1);

namespace App\Model\Machine;

/**
 * @phpstan-import-type MachineActionFailureContext from ActionFailureInterface
 */
final readonly class ActionFailure implements ActionFailureInterface
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

    public function getAction(): string
    {
        return $this->action;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
