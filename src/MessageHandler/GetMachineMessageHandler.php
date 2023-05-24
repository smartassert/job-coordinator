<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\RemoteRequestType;
use App\Event\MachineRetrievedEvent;
use App\Event\RemoteRequestCompletedEvent;
use App\Event\RemoteRequestFailedEvent;
use App\Event\RemoteRequestStartedEvent;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\Repository\JobRepository;
use App\Services\RemoteRequestFactory;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetMachineMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RemoteRequestFactory $remoteRequestFactory,
    ) {
    }

    /**
     * @throws MachineRetrievalException
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $previousMachine = $message->machine;

        $remoteRequest = $this->remoteRequestFactory->create($message->machine->id, RemoteRequestType::MACHINE_GET);

        $this->eventDispatcher->dispatch(new RemoteRequestStartedEvent($remoteRequest));

        try {
            $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new RemoteRequestCompletedEvent($remoteRequest));
            $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
                $message->authenticationToken,
                $previousMachine,
                $machine
            ));
        } catch (\Throwable $e) {
            $this->eventDispatcher->dispatch(new RemoteRequestFailedEvent($remoteRequest));

            throw new MachineRetrievalException($job, $previousMachine, $e);
        }
    }
}
