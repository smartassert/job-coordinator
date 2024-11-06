<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\RemoteRequestEntity;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

interface JobComponentHandlerInterface
{
    public function getComponentPreparation(RemoteRequestEntity $remoteRequestEntity, Job $job): ?ComponentPreparation;

    public function getRequestState(RemoteRequestEntity $remoteRequestEntity, Job $job): ?RequestState;

    public function hasFailed(RemoteRequestEntity $remoteRequestEntity, Job $job): ?bool;
}
