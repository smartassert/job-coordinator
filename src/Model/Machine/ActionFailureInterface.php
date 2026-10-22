<?php

declare(strict_types=1);

namespace App\Model\Machine;

use App\Model\SerializeToArrayInterface;

interface ActionFailureInterface extends SerializeToArrayInterface
{
    public function getAction(): string;

    public function getType(): string;

    /**
     * @return array<string, null|int|string>
     */
    public function getContext(): array;
}
