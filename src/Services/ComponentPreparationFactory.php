<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponent;
use App\Model\ComponentPreparation;
use App\Services\JobComponentHandler\JobComponentHandlerInterface;

readonly class ComponentPreparationFactory
{
    /**
     * @param JobComponentHandlerInterface[] $jobComponentHandlers
     */
    public function __construct(
        private iterable $jobComponentHandlers,
    ) {
    }

    /**
     * @return array<value-of<JobComponent>, ComponentPreparation>
     */
    public function getAll(string $jobId): array
    {
        $componentPreparations = [];

        foreach (JobComponent::cases() as $jobComponent) {
            $componentPreparation = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $componentPreparation && $jobComponentHandler->handles($jobComponent)) {
                    $componentPreparation = $jobComponentHandler->getComponentPreparation($jobId);

                    if ($componentPreparation instanceof ComponentPreparation) {
                        $componentPreparations[$jobComponent->value] = $componentPreparation;
                    }
                }
            }
        }

        return $componentPreparations;
    }
}
