<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;

interface JobPreparationInspectorInterface
{
    public function hasFailed(Job $job): bool;
}
