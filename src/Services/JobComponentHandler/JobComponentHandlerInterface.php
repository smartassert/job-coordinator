<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\JobInterface;

interface JobComponentHandlerInterface
{
    public function getComponentPreparation(JobComponent $jobComponent, JobInterface $job): ?ComponentPreparation;

    public function getRequestState(JobComponent $jobComponent, JobInterface $job): ?RequestState;

    public function hasFailed(JobComponent $jobComponent, string $jobId): ?bool;
}
