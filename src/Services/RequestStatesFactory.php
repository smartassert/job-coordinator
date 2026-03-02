<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
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
    ) {}

    /**
     * @return array<value-of<JobComponentName>, RequestState>
     */
    public function create(JobInterface $job): array
    {
        $requestStates = [];

        foreach (JobComponentName::cases() as $componentName) {
            $requestState = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $requestState && $jobComponentHandler->handles($componentName)) {
                    $requestState = $jobComponentHandler->getRequestState($job->getId());
                }
            }

            if (null === $requestState) {
                $requestState = RequestState::getDefault();
            }

            $requestStates[$componentName->value] = $requestState;
        }

        return $requestStates;
    }
}
