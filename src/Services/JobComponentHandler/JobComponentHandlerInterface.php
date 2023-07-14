<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;

interface JobComponentHandlerInterface
{
    public function getComponentPreparation(JobComponent $jobComponent, Job $job): ?ComponentPreparation;
}
