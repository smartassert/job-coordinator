<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\MessageStateMutator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class GetSerializedSuiteMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private SerializedSuiteClientInterface $serializedSuiteClient,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetSerializedSuiteMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->get(
                $message->authenticationToken,
                $message->serializedSuiteId,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteRetrievedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
