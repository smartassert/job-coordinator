<?php

declare(strict_types=1);

namespace App\Enum;

enum JobComponentName: string
{
    case MACHINE = 'machine';
    case WORKER_JOB = 'worker-job';
    case RESULTS_JOB = 'results-job';
    case SERIALIZED_SUITE = 'serialized-suite';
}
