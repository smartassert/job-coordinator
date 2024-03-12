<?php

declare(strict_types=1);

namespace App\Services;

use SmartAssert\ServiceClient\Client as ServiceClient;
use SmartAssert\WorkerClient\Client;
use SmartAssert\WorkerClient\ClientInterface;
use SmartAssert\WorkerClient\EventFactory;
use SmartAssert\WorkerClient\JobFactory;

class WorkerClientFactory
{
    public function __construct(
        private readonly ServiceClient $serviceClient,
        private readonly EventFactory $eventFactory,
        private readonly JobFactory $jobFactory,
    ) {
    }

    public function create(string $baseUrl): ClientInterface
    {
        return new Client($baseUrl, $this->serviceClient, $this->eventFactory, $this->jobFactory);
    }
}
