<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Message\MessageNotHandleableMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
abstract readonly class AbstractMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    protected function assessReadiness(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        $readiness = $this->readinessAssessor->isReady($message->getRemoteRequestType(), $message->getJobId());

        if (MessageHandlingReadiness::NOW === $readiness) {
            $message->setState(MessageState::HANDLING);
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
            $message->setState(MessageState::HALTED);
        }

        if (MessageHandlingReadiness::NEVER === $readiness) {
            $message->setState(MessageState::STOPPED);
        }

        if (MessageHandlingReadiness::NOW !== $readiness) {
            $this->handleNonHandleableMessage($message, $readiness);
        }

        return $readiness;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function handleNonHandleableMessage(
        JobRemoteRequestMessageInterface $message,
        MessageHandlingReadiness $readiness
    ): void {
        $this->logger->info(
            sprintf(
                'Failed to %s %s for job "%s": %s handleable',
                $message->getRemoteRequestType()->action->value,
                $message->getRemoteRequestType()->componentName->value,
                $message->getJobId(),
                MessageHandlingReadiness::NEVER === $readiness ? 'never' : 'not yet'
            )
        );

        $this->messageBus->dispatch(new MessageNotHandleableMessage($message, $readiness));
    }
}
