<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Message\MessageNotHandleableMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class UnhandleableMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function handle(JobRemoteRequestMessageInterface $message, MessageHandlingReadiness $readiness): void
    {
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
