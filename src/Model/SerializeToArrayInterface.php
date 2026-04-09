<?php

declare(strict_types=1);

namespace App\Model;

interface SerializeToArrayInterface extends \JsonSerializable
{
    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array;
}
