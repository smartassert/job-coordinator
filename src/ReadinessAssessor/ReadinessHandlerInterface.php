<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;

interface ReadinessHandlerInterface
{
    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness;
}
