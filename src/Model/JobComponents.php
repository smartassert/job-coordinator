<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Model\JobComponent\JobComponentInterface;

readonly class JobComponents implements \JsonSerializable
{
    /**
     * @param JobComponentInterface[] $components
     */
    public function __construct(
        private array $components,
    ) {}

    /**
     * @return array<value-of<JobComponentName>, JobComponentInterface>
     */
    public function jsonSerialize(): array
    {
        $data = [];

        foreach ($this->components as $component) {
            $data[$component->getName()->value] = $component;
        }

        return $data;
    }
}
