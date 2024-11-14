<?php

declare(strict_types=1);

namespace App\Services;

interface JobPreparationInspectorInterface
{
    public function hasFailed(string $jobId): bool;
}
