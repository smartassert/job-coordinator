<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Exception\MessageHandlerNotReadyException;
use App\Message\JobRemoteRequestMessageInterface;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
abstract readonly class AbstractMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {}

    /**
     * @throws MessageHandlerNotReadyException
     */
    protected function assessReadiness(JobRemoteRequestMessageInterface $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());

        if (MessageHandlingReadiness::NEVER === $readiness) {
            throw new MessageHandlerNotReadyException($message, MessageHandlingReadiness::NEVER);
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
            throw new MessageHandlerNotReadyException($message, MessageHandlingReadiness::EVENTUALLY);
        }
    }
}
