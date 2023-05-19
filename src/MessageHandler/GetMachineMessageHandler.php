<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
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
     * @throws ClientExceptionInterface
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws NonSuccessResponseException
     * @throws InvalidResponseTypeException
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $previousMachine = $message->machine;

        $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $previousMachine->id);

        $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
            $message->authenticationToken,
            $previousMachine,
            $machine
        ));
    }
}
