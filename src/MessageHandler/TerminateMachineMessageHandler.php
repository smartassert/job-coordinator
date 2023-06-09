<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\MachineTerminationException;
use App\Message\TerminateMachineMessage;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TerminateMachineMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerManagerClient $workerManagerClient,
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

        try {
            $this->workerManagerClient->deleteMachine($message->authenticationToken, $job->id);
        } catch (\Throwable $e) {
            throw new MachineTerminationException($job, $e);
        }
    }
}
