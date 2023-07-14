<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Model\JobComponent;
use App\Model\RequestStates;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

class RequestStatesFactory
{
    /**
     * @param JobComponentHandlerInterface[] $jobComponentHandlers
     * @param JobComponent[]                 $jobComponents
     */
    public function __construct(
        private readonly iterable $jobComponents,
        private readonly iterable $jobComponentHandlers,
    ) {
    }

    public function create(Job $job): RequestStates
    {
        $requestStates = [];

        foreach ($this->jobComponents as $jobComponent) {
            $requestState = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $requestState) {
                    $requestState = $jobComponentHandler->getRequestState($jobComponent, $job);

                    if ($requestState instanceof RequestState) {
                        $requestStates[$jobComponent->name->value] = $requestState;
                    }
                }
            }
        }

        return new RequestStates($requestStates);
    }
}
