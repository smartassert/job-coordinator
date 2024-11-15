<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Messenger\NonDelayedStamp;
use App\Repository\RemoteRequestRepository;
use App\Services\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class JobRemoteRequestMessageDispatcher
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private RemoteRequestRepository $remoteRequestRepository,
        private JobStore $jobStore,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param StampInterface[] $stamps
     */
    public function dispatch(JobRemoteRequestMessageInterface $message, array $stamps = []): ?Envelope
    {
        if (false === $message->isRepeatable()) {
            $job = $this->jobStore->retrieve($message->getJobId());
            if (null === $job) {
                return null;
            }

            $type = $message->getRemoteRequestType();

            $latestRemoteRequest = $this->remoteRequestRepository->findNewest($job->getId(), $type);
            $hasSuccessfulRequest = $this->remoteRequestRepository->hasSuccessful($job->getId(), $type);
            $latestRemoteRequestHasDisallowedState =
                $latestRemoteRequest instanceof RemoteRequest
                && in_array($latestRemoteRequest->getState(), [RequestState::REQUESTING, RequestState::PENDING]);

            // @todo test
            if ($hasSuccessfulRequest || $latestRemoteRequestHasDisallowedState) {
                $this->logger->notice(
                    'Disallow dispatch of message.',
                    [
                        'job_id' => $message->getJobId(),
                        'request_type' => (string) $type,
                        'has_existing_successful_request' => $hasSuccessfulRequest,
                        'latest_has_disallowed_state' => $latestRemoteRequestHasDisallowedState,
                    ]
                );

                return null;
            }
        }

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));

        return $this->messageBus->dispatch(new Envelope($message, $stamps));
    }

    public function dispatchWithNonDelayedStamp(JobRemoteRequestMessageInterface $message): ?Envelope
    {
        return $this->dispatch($message, [new NonDelayedStamp()]);
    }
}
