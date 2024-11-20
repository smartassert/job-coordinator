<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\ReadinessAssessor\ReadinessAssessorInterface;

abstract readonly class AbstractMessageDispatcher
{
    public function __construct(
        protected JobRemoteRequestMessageDispatcher $messageDispatcher,
        protected ReadinessAssessorInterface $readinessAssessor,
    ) {
    }

    protected function isNeverReady(string $jobId): bool
    {
        return MessageHandlingReadiness::NEVER === $this->readinessAssessor->isReady($jobId);
    }
}
