<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedMetaState array{'ended': bool, 'succeeded': bool, 'pending': bool}
 */
readonly class MetaState implements SerializeToArrayInterface
{
    public function __construct(
        public bool $ended,
        public bool $succeeded,
        public bool $pending,
    ) {}

    /**
     * @return SerializedMetaState
     */
    public function toArray(): array
    {
        return [
            'ended' => $this->ended,
            'succeeded' => $this->succeeded,
            'pending' => $this->pending,
        ];
    }
}
