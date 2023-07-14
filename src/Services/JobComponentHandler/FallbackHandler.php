<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\PreparationState;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;

class FallbackHandler implements JobComponentHandlerInterface
{
    public static function getDefaultPriority(): int
    {
        return -1;
    }

    public function getComponentPreparation(JobComponent $jobComponent, Job $job): ?ComponentPreparation
    {
        return new ComponentPreparation($jobComponent, PreparationState::PENDING);
    }
}
