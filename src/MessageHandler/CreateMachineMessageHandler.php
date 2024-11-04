<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\SerializedSuite;
use App\Event\FooEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
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
     * @throws RemoteJobActionException
     * @throws MessageHandlerJobNotFoundException
     */
    public function __invoke(CreateMachineMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            throw new MessageHandlerJobNotFoundException($message);
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        $serializedSuite = $this->serializedSuiteRepository->find($job->id);

        if (
            null === $resultsJob
            || null === $serializedSuite
            || ($serializedSuite instanceof SerializedSuite && !$serializedSuite->isPrepared())
        ) {
            $this->eventDispatcher->dispatch(new FooEvent($message));

            return;
        }

        try {
            $machine = $this->workerManagerClient->createMachine($message->authenticationToken, $job->id);

            $this->eventDispatcher->dispatch(
                new MachineCreationRequestedEvent($message->authenticationToken, $machine)
            );
        } catch (\Throwable $e) {
            throw new RemoteJobActionException($job, $e, $message);
        }
    }
}
