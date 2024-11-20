<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSerializedSuiteMessageHandler extends AbstractMessageHandler
{
    public function __construct(
        private SerializedSuiteClient $serializedSuiteClient,
        EventDispatcherInterface $eventDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($eventDispatcher, $readinessAssessor);
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(GetSerializedSuiteMessage $message): void
    {
        if (!$this->isReady($message)) {
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
