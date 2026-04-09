<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;
use App\Services\JobComponentPreparationFactory\JobComponentPreparationFactoryInterface;

readonly class ComponentPreparationFactory
{
    /**
     * @param JobComponentPreparationFactoryInterface[] $jobComponentHandlers
     */
    public function __construct(
        private iterable $jobComponentHandlers,
    ) {}

    /**
     * @return array<value-of<JobComponentName>, ComponentPreparation>
     */
    public function getAll(string $jobId): array
    {
        $componentPreparations = [];

        foreach (JobComponentName::cases() as $componentName) {
            $componentPreparation = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $componentPreparation && $jobComponentHandler->handles($componentName)) {
                    $componentPreparation = $jobComponentHandler->getComponentPreparation($jobId);
                    $componentPreparations[$componentName->value] = $componentPreparation;
                }
            }
        }

        return $componentPreparations;
    }
}
