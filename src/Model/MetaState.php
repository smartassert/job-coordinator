<?php

declare(strict_types=1);

namespace App\Model;

readonly class MetaState implements \JsonSerializable
{
    public function __construct(
        public bool $ended,
        public bool $succeeded,
        public bool $pending,
    ) {}

    /**
     * @return array{'ended': bool, 'succeeded': bool, 'pending': bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'ended' => $this->ended,
            'succeeded' => $this->succeeded,
            'pending' => $this->pending,
        ];
    }
}
