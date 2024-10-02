<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure;
use App\Enum\JobComponentName;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: PreparationStateEnum,
 *   request_states: array<RequestState>,
 *   failures: array<value-of<JobComponentName>, RemoteRequestFailure|null>
 * }
 */
class PreparationState implements \JsonSerializable
{
    /**
     * @param array<value-of<JobComponentName>, null|RemoteRequestFailure> $componentFailures
     * @param array<value-of<JobComponentName>, RequestState>              $requestStates
     */
    public function __construct(
        private readonly PreparationStateEnum $state,
        private readonly array $componentFailures,
        private readonly array $requestStates,
    ) {
    }

    /**
     * @return SerializedPreparationState
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'request_states' => $this->requestStates,
            'failures' => $this->componentFailures,
        ];
    }
}
