<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\PreparationState as PreparationStateEnum;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: PreparationStateEnum,
 *   meta_state: MetaState
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
            'state' => $this->state,
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
