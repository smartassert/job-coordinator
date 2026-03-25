<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractWorkerEvent extends Event implements JobEventInterface, MachineIpAddressInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        private readonly string $jobId,
        private readonly string $machineIpAddress,
    ) {}

    public function getMachineIpAddress(): string
    {
        return $this->machineIpAddress;
    }
}
