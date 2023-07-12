<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\PreparationState as PreparationStateEnum;

/**
 * @phpstan-import-type SerializedComponentFailures from ComponentFailures
 *
 * @phpstan-type SerializedPreparationState array{
 *   state: value-of<PreparationStateEnum>,
 *   failures?: SerializedComponentFailures
 * }
 */
class PreparationState
{
    public function __construct(
        private readonly PreparationStateEnum $state,
        private readonly ComponentFailures $componentFailures,
    ) {
    }

    /**
     * @return SerializedPreparationState
     */
    public function toArray(): array
    {
        $data = [
            'state' => $this->state->value,
        ];

        if ($this->componentFailures->has()) {
            $data['failures'] = $this->componentFailures->toArray();
        }

        return $data;
    }
}
