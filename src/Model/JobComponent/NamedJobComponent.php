<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Enum\JobComponentName;
use App\Model\SerializeToArrayInterface;

readonly class NamedJobComponent implements NamedJobComponentInterface
{
    public function __construct(
        private JobComponentName $name,
        private ?SerializeToArrayInterface $component,
    ) {}

    public function getName(): JobComponentName
    {
        return $this->name;
    }

    public function jsonSerialize(): ?array
    {
        return $this->component?->jsonSerialize();
    }
}
