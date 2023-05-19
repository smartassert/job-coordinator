<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineRetrievedEvent;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetMachineMessageHandler
{
    public function __construct(
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws MachineRetrievalException
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $previousMachine = $message->machine;

        try {
            $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $previousMachine->id);

            $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
                $message->authenticationToken,
                $previousMachine,
                $machine
            ));
        } catch (\Throwable $e) {
            throw new MachineRetrievalException($previousMachine, $e);
        }
    }
}
