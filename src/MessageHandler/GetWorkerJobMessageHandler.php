<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WorkerComponentState;
use App\Event\MessageNotHandleableEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\Repository\WorkerComponentStateRepository;
use App\Services\JobStore;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetWorkerJobMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
        private readonly WorkerClientFactory $workerClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(GetWorkerJobMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $applicationState = $this->workerComponentStateRepository->getApplicationState($job);
        if ($applicationState instanceof WorkerComponentState && $applicationState->isEndState()) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $this->eventDispatcher->dispatch(new WorkerStateRetrievedEvent(
                $job->getId(),
                $message->machineIpAddress,
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
