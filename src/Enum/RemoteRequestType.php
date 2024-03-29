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
    case MACHINE_TERMINATE = 'machine/terminate';
    case MACHINE_STATE_GET = 'machine/state/get';
    private const REPEATABLE_TYPES = [
        self::MACHINE_GET,
        self::SERIALIZED_SUITE_GET,
        self::RESULTS_STATE_GET,
        self::MACHINE_STATE_GET,
        self::SERIALIZED_SUITE_READ,
    ];

    public function isRepeatable(): bool
    {
        return in_array($this, self::REPEATABLE_TYPES);
    }
}
