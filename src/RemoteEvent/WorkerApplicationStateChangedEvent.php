<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Model\JobInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use Symfony\Component\RemoteEvent\RemoteEvent;

class WorkerApplicationStateChangedEvent extends RemoteEvent
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        string $name,
        string $id,
        array $payload,
        public readonly JobInterface $job,
        public readonly ApplicationState $applicationState,
    ) {
        parent::__construct($name, $id, $payload);
    }
}
