<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private SerializedSuiteClientInterface $serializedSuiteClient,
        private EventDispatcherInterface $eventDispatcher,
        private AuthenticationTokenProvider $authenticationTokenProvider,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $authenticationToken,
                $message->getJobId(),
                $message->suiteId,
                null,
                $message->parameters,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteCreatedEvent(
                $authenticationToken,
                $message->getJobId(),
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
