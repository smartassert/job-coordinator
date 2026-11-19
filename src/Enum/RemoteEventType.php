<?php

declare(strict_types=1);

namespace App\Enum;

enum RemoteEventType: string
{
    case RESULTS_JOB_STATE_CHANGED = 'results.job.state_changed';
    case SERIALIZED_SUITE_STATE_CHANGED = 'sources.serialized_suite.state_changed';
    case MACHINE_STATE_CHANGED = 'worker-manager.machine.state_changed';
    case WORKER_EVENT = 'worker.event';
}
