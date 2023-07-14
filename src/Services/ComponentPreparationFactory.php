<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

class ComponentPreparationFactory
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

    /**
     * @return array<value-of<JobComponentName>, ComponentPreparation>
     */
    public function getAll(Job $job): array
    {
        $componentPreparations = [];

        foreach ($this->jobComponents as $jobComponent) {
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
