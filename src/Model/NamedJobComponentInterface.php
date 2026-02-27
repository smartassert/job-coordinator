<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;

interface NamedJobComponentInterface extends \JsonSerializable
{
    public function getName(): JobComponentName;

    /**
     * @return null|array<mixed>
     */
    public function jsonSerialize(): ?array;
}
