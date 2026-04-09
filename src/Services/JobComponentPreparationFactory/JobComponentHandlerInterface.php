<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;

interface JobComponentHandlerInterface
{
    public function handles(JobComponentName $componentName): bool;

    public function getComponentPreparation(string $jobId): ComponentPreparation;
}
