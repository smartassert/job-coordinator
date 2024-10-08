<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineRetrievedEvent;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\Repository\JobRepository;
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

        try {
            $machine = $this->workerManagerClient->getMachine($message->authenticationToken, $message->getJobId());

            $this->eventDispatcher->dispatch(new MachineRetrievedEvent(
                $message->authenticationToken,
                $previousMachine,
                $machine
            ));
        } catch (\Throwable $e) {
            throw new MachineRetrievalException($job, $previousMachine, $e, $message);
        }
    }
}
