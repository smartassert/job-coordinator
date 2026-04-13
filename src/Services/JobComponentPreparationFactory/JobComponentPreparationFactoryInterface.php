<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Model\ComponentPreparation;

interface JobComponentPreparationFactoryInterface
{
    public function getComponentPreparation(string $jobId): ComponentPreparation;
}
