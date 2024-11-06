<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\RemoteRequestEntity;
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
     * @return array<value-of<RemoteRequestEntity>, ComponentPreparation>
     */
    public function getAll(Job $job): array
    {
        $componentPreparations = [];

        foreach (RemoteRequestEntity::cases() as $remoteRequestEntity) {
            $componentPreparation = null;

            foreach ($this->jobComponentHandlers as $jobComponentHandler) {
                if (null === $componentPreparation) {
                    $componentPreparation = $jobComponentHandler->getComponentPreparation($remoteRequestEntity, $job);

                    if ($componentPreparation instanceof ComponentPreparation) {
                        $componentPreparations[$remoteRequestEntity->value] = $componentPreparation;
                    }
                }
            }
        }

        return $componentPreparations;
    }
}
