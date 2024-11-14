<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

class FallbackHandler implements JobComponentHandlerInterface
{
    public static function getDefaultPriority(): int
    {
        return -1;
    }

    public function getComponentPreparation(JobComponent $jobComponent, string $jobId): ?ComponentPreparation
    {
        return new ComponentPreparation($jobComponent, PreparationState::PENDING);
    }

    public function getRequestState(JobComponent $jobComponent, string $jobId): ?RequestState
    {
        return RequestState::PENDING;
    }

    public function hasFailed(JobComponent $jobComponent, string $jobId): bool
    {
        return false;
    }
}
