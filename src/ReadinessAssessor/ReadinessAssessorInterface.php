<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;

interface ReadinessAssessorInterface
{
    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness;
}
