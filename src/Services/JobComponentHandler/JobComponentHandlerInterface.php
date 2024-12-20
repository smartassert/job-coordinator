<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

interface JobComponentHandlerInterface
{
    public function handles(JobComponent $jobComponent): bool;

    public function getComponentPreparation(string $jobId): ?ComponentPreparation;

    public function getRequestState(string $jobId): ?RequestState;

    public function hasFailed(string $jobId): ?bool;
}
