<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\WorkerStateRetrievedEvent;
use App\Exception\WorkerStateRetrievalException;
use App\Message\GetWorkerStateMessage;
use App\Repository\JobRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetWorkerStateMessageHandler
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerClientFactory $workerClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws WorkerStateRetrievalException
     */
    public function __invoke(GetWorkerStateMessage $message): void
    {
        $job = $this->jobRepository->find($message->getJobId());
        if (null === $job) {
            return;
        }

        $workerClient = $this->workerClientFactory->create('http://' . $message->machineIpAddress);

        try {
            $this->eventDispatcher->dispatch(new WorkerStateRetrievedEvent(
                $job->id,
                $message->machineIpAddress,
                $workerClient->getApplicationState()
            ));
        } catch (\Throwable $e) {
            throw new WorkerStateRetrievalException($job, $e);
        }
    }
}
