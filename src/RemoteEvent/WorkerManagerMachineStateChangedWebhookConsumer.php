<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Event\MachineRetrievedEvent;
use App\Repository\MachineRepository;
use App\Services\MachineModelFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Factory\MachineFactory as WorkerManagerClientMachineFactory;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(self::NAME)]
final readonly class WorkerManagerMachineStateChangedWebhookConsumer implements ConsumerInterface
{
    public const string NAME = 'worker-manager.machine.state_changed';

    public function __construct(
        private WorkerManagerClientMachineFactory $workerManagerClientMachineFactory,
        private MachineRepository $machineRepository,
        private EventDispatcherInterface $eventDispatcher,
        private MachineModelFactory $machineModelFactory,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (self::NAME !== $event->getName()) {
            return;
        }

        $workerManagerMachine = $this->workerManagerClientMachineFactory->create($event->getPayload());
        if (null === $workerManagerMachine) {
            return;
        }

        $machineEntity = $this->machineRepository->find($workerManagerMachine->id);
        if (null === $machineEntity) {
            return;
        }

        $machineModel = $this->machineModelFactory->createFromWorkerManagerClientMachine($workerManagerMachine);

        $this->eventDispatcher->dispatch(new MachineRetrievedEvent($machineModel));
    }
}
