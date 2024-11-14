<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateMachineMessageHandler
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private WorkerManagerClient $workerManagerClient,
        private EventDispatcherInterface $eventDispatcher,
        private MachineRepository $machineRepository,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        if ($this->machineRepository->has($message->getJobId())) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($message->getJobId());
        if (
            !$this->resultsJobRepository->has($message->getJobId())
            || null === $serializedSuite
            || !$serializedSuite->isPrepared()
        ) {
            $this->eventDispatcher->dispatch(new MessageNotYetHandleableEvent($message));

            return;
        }

        try {
            $machine = $this->workerManagerClient->createMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(
                new MachineCreationRequestedEvent($message->authenticationToken, $machine)
            );
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($message->getJobId(), $e, $message);
        }
    }
}
