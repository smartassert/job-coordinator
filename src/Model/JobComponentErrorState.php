<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;

readonly class JobComponentErrorState implements \Stringable
{
    public function __construct(
        private JobComponentName $component,
        private string $errorState,
    ) {}

    public function __toString(): string
    {
        return sprintf(
            '%s:%s',
            $this->component->value,
            $this->errorState,
        );
    }
}
