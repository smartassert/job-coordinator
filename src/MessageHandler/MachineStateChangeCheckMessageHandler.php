<?php

namespace App\MessageHandler;

use App\Event\MachineStateChangeEvent;
use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseContentException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MachineStateChangeCheckMessageHandler
{
    public function __construct(
        private readonly MachineStateChangeCheckMessageDispatcher $messageDispatcher,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseContentException
     * @throws InvalidResponseDataException
     * @throws NonSuccessResponseException
     */
    public function __invoke(MachineStateChangeCheckMessage $message): void
    {
        $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $message->machineId);

        if ($message->currentState !== $machine->state) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent(
                $message->currentState,
                $machine->state
            ));
        }

        if (!$machine->hasEndState) {
            $this->messageDispatcher->dispatch($message->withCurrentState($machine->state));
        }
    }
}
