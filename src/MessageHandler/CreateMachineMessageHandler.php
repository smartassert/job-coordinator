<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\MachineCreationRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateMachineMessageHandler
{
    public function __construct(
        private JobRepository $jobRepository,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private WorkerManagerClient $workerManagerClient,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws MachineCreationException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuite) {
            return;
        }

        if (!$serializedSuite->isPrepared()) {
            return;
        }

        try {
            $machine = $this->workerManagerClient->createMachine($message->authenticationToken, $job->id);

            $this->eventDispatcher->dispatch(
                new MachineCreationRequestedEvent($message->authenticationToken, $machine)
            );
        } catch (\Throwable $e) {
            throw new MachineCreationException($job, $e, $message);
        }
    }
}
