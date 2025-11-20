<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;

interface ReadinessAssessorInterface
{
    public function isReady(string $jobId): MessageHandlingReadiness;

    public function handles(RemoteRequestType $type): bool;
}
