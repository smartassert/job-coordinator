<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateMachineMessageHandler
{
    public function __construct(
        private WorkerManagerClient $workerManagerClient,
        private EventDispatcherInterface $eventDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {
    }

    /**
     * @throws RemoteJobActionException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());

        if (MessageHandlingReadiness::NEVER === $readiness) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return;
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
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
