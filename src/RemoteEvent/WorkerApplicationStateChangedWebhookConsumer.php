<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Enum\RemoteEventType;
use App\Event\WorkerJobRetrievedEvent;
use App\Repository\JobRepository;
use App\Request\WorkerApplicationStateChange\Factory;
use App\Request\WorkerApplicationStateChange\InvalidRequestException;
use App\Request\WorkerApplicationStateChange\Validator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerClient\Factory\ApplicationStateFactory;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(RemoteEventType::WORKER_STATE_CHANGED->value)]
final readonly class WorkerApplicationStateChangedWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private Factory $requestFactory,
        private Validator $requestValidator,
        private JobRepository $jobRepository,
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (RemoteEventType::WORKER_STATE_CHANGED->value !== $event->getName()) {
            return;
        }

        $applicationStateChangeRequest = $this->requestFactory->create($event->getPayload());

        try {
            $validatedRequest = $this->requestValidator->validate($applicationStateChangeRequest);
        } catch (InvalidRequestException $e) {
            return;
        }

        $job = $this->jobRepository->find($validatedRequest->jobId);
        if (null === $job) {
            return;
        }

        $applicationState = $this->applicationStateFactory->create($validatedRequest->stateData);
        if (null === $applicationState) {
            return;
        }

        $this->eventDispatcher->dispatch(new WorkerJobRetrievedEvent($job->getId(), $applicationState));
    }
}
