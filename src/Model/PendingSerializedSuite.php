<?php

declare(strict_types=1);

namespace App\Model;

class PendingSerializedSuite implements SerializedSuiteInterface
{
    public function getState(): ?string
    {
        return null;
    }
}
