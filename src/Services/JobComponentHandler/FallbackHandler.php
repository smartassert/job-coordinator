<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestEntity;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

class FallbackHandler implements JobComponentHandlerInterface
{
    public static function getDefaultPriority(): int
    {
        return -1;
    }

    public function getComponentPreparation(RemoteRequestEntity $remoteRequestEntity, Job $job): ?ComponentPreparation
    {
        return new ComponentPreparation($remoteRequestEntity, PreparationState::PENDING);
    }

    public function getRequestState(RemoteRequestEntity $remoteRequestEntity, Job $job): ?RequestState
    {
        return RequestState::PENDING;
    }

    public function hasFailed(RemoteRequestEntity $remoteRequestEntity, Job $job): bool
    {
        return false;
    }
}
