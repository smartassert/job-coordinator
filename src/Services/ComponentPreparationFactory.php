<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Enum\RemoteRequestType;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

class ComponentPreparationFactory
{
    /**
     * @param JobComponentHandlerInterface[] $jobComponentHandlers
     */
    public function __construct(
        private readonly iterable $jobComponentHandlers,
    ) {
    }

    /**
     * @return array<value-of<JobComponentName>, ComponentPreparation>
     */
    public function getAll(Job $job): array
    {
        $jobComponents = [
            new JobComponent(JobComponentName::RESULTS_JOB, RemoteRequestType::RESULTS_CREATE),
            new JobComponent(JobComponentName::SERIALIZED_SUITE, RemoteRequestType::SERIALIZED_SUITE_CREATE),
            new JobComponent(JobComponentName::MACHINE, RemoteRequestType::MACHINE_CREATE),
            new JobComponent(JobComponentName::WORKER_JOB, RemoteRequestType::MACHINE_START_JOB),
        ];

        $componentPreparations = [];

        foreach ($jobComponents as $jobComponent) {
            $componentPreparation = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $componentPreparation) {
                    $componentPreparation = $jobComponentHandler->getComponentPreparation($jobComponent, $job);

                    if ($componentPreparation instanceof ComponentPreparation) {
                        $componentPreparations[$jobComponent->name->value] = $componentPreparation;
                    }
                }
            }
        }

        return $componentPreparations;
    }
}
