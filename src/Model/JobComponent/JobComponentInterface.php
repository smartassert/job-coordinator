<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Enum\JobComponentName;

interface JobComponentInterface extends \JsonSerializable
{
    public function getName(): JobComponentName;

    /**
     * @return null|array<mixed>
     */
    public function jsonSerialize(): ?array;
}
