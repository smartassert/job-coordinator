<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponent;
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
     * @return array<value-of<JobComponent>, RequestState>
     */
    public function create(Job $job): array
    {
        $requestStates = [];

        foreach (JobComponent::cases() as $jobComponent) {
            $requestState = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $requestState) {
                    $requestState = $jobComponentHandler->getRequestState($jobComponent, $job);

                    if ($requestState instanceof RequestState) {
                        $requestStates[$jobComponent->value] = $requestState;
                    }
                }
            }
        }

        return $requestStates;
    }
}
