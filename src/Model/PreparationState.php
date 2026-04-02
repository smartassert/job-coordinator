<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: PreparationStateEnum,
 *   request_states: array<RequestState>,
 *   meta_state: MetaState
 * }
 */
readonly class PreparationState implements \JsonSerializable
{
    /**
     * @param array<value-of<JobComponentName>, RequestState> $requestStates
     */
    public function __construct(
        private PreparationStateEnum $state,
        private array $requestStates,
    ) {}

    /**
     * @return SerializedPreparationState
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'request_states' => $this->requestStates,
            'meta_state' => $this->getMetaState(),
        ];
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(
            PreparationStateEnum::isEndState($this->state),
            PreparationStateEnum::isSuccessState($this->state)
        );
    }
}
