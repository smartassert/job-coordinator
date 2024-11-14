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
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
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
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $serializedSuiteEntity = $this->serializedSuiteRepository->find($job->getId());
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
                $job->getId(),
                $serializedSuiteModel
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
