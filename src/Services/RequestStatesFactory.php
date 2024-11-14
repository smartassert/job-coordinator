<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\JobInterface;
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
    public function create(JobInterface $job): array
    {
        $requestStates = [];

        foreach (JobComponent::cases() as $jobComponent) {
            $requestState = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $requestState) {
                    $requestState = $jobComponentHandler->getRequestState($jobComponent, $job->getId());

                    if ($requestState instanceof RequestState) {
                        $requestStates[$jobComponent->value] = $requestState;
                    }
                }
            }
        }

        return $requestStates;
    }
}
