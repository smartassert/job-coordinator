<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\PreparationState as PreparationStateEnum;

/**
 * @phpstan-import-type SerializedComponentFailures from ComponentFailures
 * @phpstan-import-type SerializedRequestStates from RequestStates
 *
 * @phpstan-type SerializedPreparationState array{
 *   state: value-of<PreparationStateEnum>,
 *   request_states: SerializedRequestStates,
 *   failures?: SerializedComponentFailures
 * }
 */
class PreparationState
{
    public function __construct(
        private readonly PreparationStateEnum $state,
        private readonly ComponentFailures $componentFailures,
        private readonly RequestStates $requestStates,
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

        $data['request_states'] = $this->requestStates->toArray();

        if ($this->componentFailures->has()) {
            $data['failures'] = $this->componentFailures->toArray();
        }

        return $data;
    }
}
