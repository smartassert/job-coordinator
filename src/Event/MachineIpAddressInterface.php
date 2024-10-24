<?php

declare(strict_types=1);

namespace App\Event;

interface MachineIpAddressInterface
{
    /**
     * @return non-empty-string
     */
    public function getMachineIpAddress(): string;
}
