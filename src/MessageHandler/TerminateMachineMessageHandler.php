<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineTerminationRequestedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\Repository\ResultsJobRepository;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TerminateMachineMessageHandler
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(TerminateMachineMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $resultsJob = $this->resultsJobRepository->find($job->getId());
        if (null === $resultsJob || !$resultsJob->hasEndState()) {
            return;
        }

        try {
            $this->workerManagerClient->deleteMachine($message->authenticationToken, $job->getId());

            $this->eventDispatcher->dispatch(new MachineTerminationRequestedEvent($job->getId()));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
