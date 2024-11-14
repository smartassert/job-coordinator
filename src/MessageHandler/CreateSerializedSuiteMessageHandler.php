<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MessageNotHandleableEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateSerializedSuiteMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly SerializedSuiteClient $serializedSuiteClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(CreateSerializedSuiteMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        if ($this->serializedSuiteRepository->has($job->getId())) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        try {
            $serializedSuite = $this->serializedSuiteClient->create(
                $message->authenticationToken,
                $job->getId(),
                $job->getSuiteId(),
                $message->parameters,
            );

            $this->eventDispatcher->dispatch(new SerializedSuiteCreatedEvent(
                $message->authenticationToken,
                $job->getId(),
                $serializedSuite
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
