<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        if ($this->serializedSuiteRepository->has($message->getJobId())) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

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
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
