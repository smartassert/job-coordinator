<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\RemoteRequest;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\JobRemoteRequestMessageInterface;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

class JobRemoteRequestMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @param StampInterface[] $stamps
     *
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatch(JobRemoteRequestMessageInterface $message, array $stamps = []): ?Envelope
    {
        if (false === $message->isRepeatable()) {
            $job = $this->jobRepository->find($message->getJobId());
            if (null === $job) {
                return null;
            }

            $existingRemoteRequest = $this->remoteRequestRepository->getFirstForJobAndType(
                $job,
                $message->getRemoteRequestType(),
            );

            if ($existingRemoteRequest instanceof RemoteRequest) {
                throw new NonRepeatableMessageAlreadyDispatchedException($job, $existingRemoteRequest);
            }
        }

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));

        return $this->messageBus->dispatch(new Envelope($message, $stamps));
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchWithNonDelayedStamp(JobRemoteRequestMessageInterface $message): ?Envelope
    {
        return $this->dispatch($message, [new NonDelayedStamp()]);
    }
}
