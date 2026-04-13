<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationStateRetriever;

use App\Enum\PreparationState;

interface JobComponentPreparationStateRetrieverInterface
{
    public function get(string $jobId): PreparationState;
}
