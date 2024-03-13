<?php

declare(strict_types=1);

namespace App\Model;

interface MachineInterface
{
    /**
     * @return ?non-empty-string
     */
    public function getStateCategory(): ?string;

    /**
     * @return ?non-empty-string
     */
    public function getIp(): ?string;
}
