<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\MessageHandlerTargetEntityNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     * @throws MessageHandlerTargetEntityNotFoundException
     */
    public function __invoke(GetSerializedSuiteMessage $message): void
    {
        $serializedSuiteEntity = $this->serializedSuiteRepository->find($message->getJobId());
        if (null === $serializedSuiteEntity) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            throw new MessageHandlerTargetEntityNotFoundException($message, 'SerializedSuite');
        }

        if ($serializedSuiteEntity->hasEndState()) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $serializedSuiteModel = $this->serializedSuiteClient->get(
                $message->authenticationToken,
                $message->serializedSuiteId,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteRetrievedEvent(
                $message->authenticationToken,
                $message->getJobId(),
                $serializedSuiteModel
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
