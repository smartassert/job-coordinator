<?php

declare(strict_types=1);

namespace App\Model;

readonly class FooJob implements \JsonSerializable
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
