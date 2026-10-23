<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MachineModelFactory;
use App\Services\MessageStateMutator;
use App\Services\WorkerManagerClientWrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class GetMachineMessageHandler
{
    public function __construct(
        private ReadinessAssessorInterface $readinessAssessor,
        private MessageStateMutator $messageStateMutator,
        private WorkerManagerClientWrapper $workerManagerClientWrapper,
        private EventDispatcherInterface $eventDispatcher,
        private AuthenticationTokenProvider $authenticationTokenProvider,
        private MachineRepository $machineRepository,
        private MachineModelFactory $machineModelFactory,
    ) {}

    /**
     * @throws RemoteJobActionException
     * @throws ExceptionInterface
     */
    public function __invoke(GetMachineMessage $message): void
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        $this->messageStateMutator->set($message, $readiness);

        if (MessageHandlingReadiness::NOW !== $readiness) {
            return;
        }

        $previousMachineEntity = $this->machineRepository->find($message->getJobId());
        if (null === $previousMachineEntity) {
            return;
        }

        $previousMachine = $this->machineModelFactory->createFromMachineEntity($previousMachineEntity);

        $authenticationToken = $this->authenticationTokenProvider->get($message->getJobId());
        if (null === $authenticationToken) {
            return;
        }

        try {
            $machine = $this->workerManagerClientWrapper->get($authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
                $previousMachine,
                $machine,
            ));
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($e, $message);
        }
    }
}
