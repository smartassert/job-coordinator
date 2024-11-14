<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

interface JobComponentHandlerInterface
{
    public function getComponentPreparation(JobComponent $jobComponent, string $jobId): ?ComponentPreparation;

    public function getRequestState(JobComponent $jobComponent, string $jobId): ?RequestState;

    public function hasFailed(JobComponent $jobComponent, string $jobId): ?bool;
}
