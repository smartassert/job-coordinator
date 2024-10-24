<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ResultsJob;
use App\Event\MachineTerminationRequestedEvent;
use App\Exception\MachineTerminationException;
use App\Message\TerminateMachineMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TerminateMachineMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly WorkerManagerClient $workerManagerClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws MachineTerminationException
     */
    public function __invoke(TerminateMachineMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($message->getJobId());
        if ($resultsJob instanceof ResultsJob && $resultsJob->hasEndState()) {
            return;
        }

        try {
            $this->workerManagerClient->deleteMachine($message->authenticationToken, $job->id);

            $this->eventDispatcher->dispatch(new MachineTerminationRequestedEvent(
                $message->authenticationToken,
                $job->id
            ));
        } catch (\Throwable $e) {
            throw new MachineTerminationException($job, $e, $message);
        }
    }
}
