<?php

declare(strict_types=1);

namespace App\Enum;

enum RemoteRequestType: string
{
    case MACHINE_CREATE = 'machine/create';
    case MACHINE_GET = 'machine/get';
    case MACHINE_START_JOB = 'machine/start-job';
    case RESULTS_CREATE = 'results/create';
    case SERIALIZED_SUITE_CREATE = 'serialized-suite/create';
    case SERIALIZED_SUITE_READ = 'serialized-suite/read';
    case SERIALIZED_SUITE_GET = 'serialized-suite/get';
    case RESULTS_STATE_GET = 'results/state/get';
}
