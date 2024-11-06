<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\RemoteRequestEntity;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

readonly class JobPreparationInspector implements JobPreparationInspectorInterface
{
    /**
     * @param JobComponentHandlerInterface[] $jobComponentHandlers
     */
    public function __construct(
        private iterable $jobComponentHandlers,
    ) {
    }

    public function hasFailed(Job $job): bool
    {
        foreach (RemoteRequestEntity::cases() as $remoteRequestEntity) {
            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                $hasJobComponentFailed = $jobComponentHandler->hasFailed($remoteRequestEntity, $job);

                if (true === $hasJobComponentFailed) {
                    return true;
                }
            }
        }

        return false;
    }
}
