<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\RequestState;
use App\Event\MachineRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateMachineMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws MachineCreationException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if (null === $job) {
            return;
        }

        $job->setMachineRequestState(RequestState::REQUESTING);
        $this->jobRepository->add($job);

        try {
            $machine = $this->workerManagerClient->createMachine($message->authenticationToken, $message->jobId);

            $this->eventDispatcher->dispatch(new MachineRequestedEvent($message->authenticationToken, $machine));
        } catch (\Throwable $e) {
            $job->setMachineRequestState(RequestState::HALTED);
            $this->jobRepository->add($job);

            throw new MachineCreationException($job, $e);
        }
    }
}
