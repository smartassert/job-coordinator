<?php

declare(strict_types=1);

namespace App\Request\WorkerApplicationStateChange;

namespace App\Request\WorkerApplicationStateChange;

class Factory
{
    /**
     * @param array<mixed> $data
     */
    public function create(array $data): Request
    {
        $job = $data[Request::KEY_JOB] ?? null;
        $job = is_string($job) && '' !== $job ? $job : null;

        $stateData = $data[Request::KEY_STATE_DATA] ?? [];
        $stateData = is_array($stateData) ? $stateData : [];

        return new Request($job, $stateData);
    }
}
