<?php

declare(strict_types=1);

namespace App\Services;

use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\WorkerClient\Client;
use SmartAssert\WorkerClient\Factory\ApplicationStateFactory;
use SmartAssert\WorkerClient\Factory\EventFactory;
use SmartAssert\WorkerClient\Factory\JobCreationExceptionFactory;
use SmartAssert\WorkerClient\Factory\JobFactory;

class WorkerClientFactory
{
    public function __construct(
        private readonly ServiceClient $serviceClient,
        private readonly EventFactory $eventFactory,
        private readonly JobFactory $jobFactory,
        private readonly ApplicationStateFactory $applicationStateFactory,
        private readonly JobCreationExceptionFactory $jobCreationExceptionFactory,
    ) {}

    public function create(string $ipAddress): Client
    {
        return new Client(
            'https://' . $ipAddress,
            $this->serviceClient,
            $this->eventFactory,
            $this->jobFactory,
            $this->applicationStateFactory,
            $this->jobCreationExceptionFactory,
        );
    }
}
