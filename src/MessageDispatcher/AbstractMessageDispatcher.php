<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\ReadinessAssessor\FooReadinessAssessorInterface;

abstract readonly class AbstractMessageDispatcher
{
    public function __construct(
        protected JobRemoteRequestMessageDispatcher $messageDispatcher,
        protected FooReadinessAssessorInterface $readinessAssessor,
    ) {}

    protected function isNeverReady(JobRemoteRequestMessageInterface $message): bool
    {
        $readiness = $this->readinessAssessor->isReady($message->getRemoteRequestType(), $message->getJobId());

        return MessageHandlingReadiness::NEVER === $readiness;
    }
}
