<?php

declare(strict_types=1);

namespace App\Model;

class PendingMachine implements MachineInterface
{
    public function getStateCategory(): ?string
    {
        return null;
    }

    public function getIp(): ?string
    {
        return null;
    }
}
