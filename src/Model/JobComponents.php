<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;

readonly class JobComponents implements \JsonSerializable
{
    /**
     * @param NamedJobComponentInterface[] $components
     */
    public function __construct(
        private array $components,
    ) {}

    /**
     * @return array<value-of<JobComponentName>, NamedJobComponentInterface>
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
