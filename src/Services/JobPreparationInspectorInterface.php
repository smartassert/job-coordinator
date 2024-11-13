<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobInterface;

interface JobPreparationInspectorInterface
{
    public function hasFailed(JobInterface $job): bool;
}
