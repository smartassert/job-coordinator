<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\RemoteRequestEntity;
use App\Enum\RequestState;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

class RequestStatesFactory
{
    /**
     * @param JobComponentHandlerInterface[] $jobComponentHandlers
     */
    public function __construct(
        private readonly iterable $jobComponentHandlers,
    ) {
    }

    /**
     * @return array<value-of<RemoteRequestEntity>, RequestState>
     */
    public function create(Job $job): array
    {
        $requestStates = [];

        foreach (RemoteRequestEntity::cases() as $remoteRequestEntity) {
            $requestState = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $requestState) {
                    $requestState = $jobComponentHandler->getRequestState($remoteRequestEntity, $job);

                    if ($requestState instanceof RequestState) {
                        $requestStates[$remoteRequestEntity->value] = $requestState;
                    }
                }
            }
        }

        return $requestStates;
    }
}
