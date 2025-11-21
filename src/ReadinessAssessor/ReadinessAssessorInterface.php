<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;

interface ReadinessAssessorInterface
{
    public function isReady(RemoteRequestType $type, string $jobId): MessageHandlingReadiness;
}
