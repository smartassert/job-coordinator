<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Exception\MessageHandlerNotReadyException;
use App\Message\JobRemoteRequestMessageInterface;
use App\ReadinessAssessor\FooReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
abstract readonly class AbstractMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        private FooReadinessAssessorInterface $readinessAssessor,
    ) {}

    /**
     * @throws MessageHandlerNotReadyException
     */
    protected function assessReadiness(JobRemoteRequestMessageInterface $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getRemoteRequestType(), $message->getJobId());

        if (MessageHandlingReadiness::NEVER === $readiness) {
            throw new MessageHandlerNotReadyException($message, MessageHandlingReadiness::NEVER);
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
            throw new MessageHandlerNotReadyException($message, MessageHandlingReadiness::EVENTUALLY);
        }
    }
}
