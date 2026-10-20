<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\PreparationState as PreparationStateEnum;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 *
 * @phpstan-type SerializedPreparationState array{
 *   state: value-of<PreparationStateEnum>,
 *   meta_state: SerializedMetaState
 * }
 */
readonly class PreparationState implements \JsonSerializable
{
    public function __construct(
        private PreparationStateEnum $state,
    ) {}

    /**
     * @return SerializedPreparationState
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state->value,
            'meta_state' => $this->getMetaState()->jsonSerialize(),
        ];
    }

    public function getMetaState(): MetaState
    {
        return new MetaState(
            PreparationStateEnum::isEndState($this->state),
            PreparationStateEnum::isSuccessState($this->state),
            PreparationStateEnum::isPendingState($this->state),
        );
    }
}
