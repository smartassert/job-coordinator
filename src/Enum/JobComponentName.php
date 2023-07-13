<?php

declare(strict_types=1);

namespace App\Enum;

enum JobComponentName: string
{
    case RESULTS_JOB = 'results_job';
    case SERIALIZED_SUITE = 'serialized_suite';
    case MACHINE = 'machine';
    case WORKER_JOB = 'worker_job';
}
