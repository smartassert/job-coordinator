<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: value-of<PreparationStateEnum>,
 *   request_states: array<RequestState>,
 *   failures: ComponentFailures
 * }
 */
class PreparationState implements \JsonSerializable
{
    /**
     * @param array<value-of<JobComponentName>, RequestState> $requestStates
     */
    public function __construct(
        private readonly PreparationStateEnum $state,
        private readonly ComponentFailures $componentFailures,
        private readonly array $requestStates,
    ) {
    }

    /**
     * @return SerializedPreparationState
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state->value,
            'request_states' => $this->requestStates,
            'failures' => $this->componentFailures,
        ];
    }
}
