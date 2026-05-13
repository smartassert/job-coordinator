<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateSerializedSuiteMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private ReadinessHandlerInterface $readinessAssessor,
        private SerializedSuiteClient $serializedSuiteClient,
        EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
    ) {
        parent::__construct($eventDispatcher, $messageBus, $logger);
    }

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message);
        $this->setMessageState($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $message->authenticationToken,
                $message->getJobId(),
                $message->suiteId,
                $message->parameters,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteCreatedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
