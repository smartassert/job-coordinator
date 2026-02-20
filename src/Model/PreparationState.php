<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure;
use App\Enum\JobComponent;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: PreparationStateEnum,
 *   request_states: array<RequestState>,
 *   failures: array<value-of<JobComponent>, RemoteRequestFailure>,
 *   meta_state: MetaState
 * }
 */
readonly class PreparationState implements \JsonSerializable
{
    /**
     * @param array<value-of<JobComponent>, RequestState>         $requestStates
     * @param array<value-of<JobComponent>, RemoteRequestFailure> $componentFailures
     */
    public function __construct(
        private PreparationStateEnum $state,
        private array $requestStates,
        private array $componentFailures,
    ) {}

    /**
     * @return SerializedPreparationState
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'request_states' => $this->requestStates,
            'failures' => $this->componentFailures,
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
