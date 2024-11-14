<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateMachineMessageHandler
{
    public function __construct(
        private JobStore $jobStore,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private WorkerManagerClient $workerManagerClient,
        private EventDispatcherInterface $eventDispatcher,
        private MachineRepository $machineRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $job = $this->jobStore->retrieve($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $jobId = $message->getJobId();

        if ($this->machineRepository->has($jobId)) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($jobId);
        if (
            !$this->resultsJobRepository->has($jobId)
            || null === $serializedSuite
            || !$serializedSuite->isPrepared()
        ) {
            $this->eventDispatcher->dispatch(new MessageNotYetHandleableEvent($message));

            return;
        }

        try {
            $machine = $this->workerManagerClient->createMachine($message->authenticationToken, $jobId);

            $this->eventDispatcher->dispatch(
                new MachineCreationRequestedEvent($message->authenticationToken, $machine)
            );
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
