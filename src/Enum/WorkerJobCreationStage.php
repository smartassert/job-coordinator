<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerJobCreationStage: string
{
    case SERIALIZED_SUITE_READ = 'serialized-suite-read';
    case WORKER_JOB_CREATE = 'worker-job-create';
}
