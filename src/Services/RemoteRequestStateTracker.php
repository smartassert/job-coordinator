<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\RemoteRequestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent as FailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent as HandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent as ReceivedEvent;

class RemoteRequestStateTracker implements EventSubscriberInterface
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFactory $remoteRequestFactory,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FailedEvent::class => [
                ['setRemoteRequestState', 10000],
            ],
            HandledEvent::class => [
                ['setRemoteRequestState', 10000],
            ],
            ReceivedEvent::class => [
                ['setRemoteRequestState', 10000],
            ],
        ];
    }

    public function setRemoteRequestState(FailedEvent|HandledEvent|ReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        $jobId = $message->getJobId();
        $remoteRequestType = $message->getRemoteRequestType();
        $requestState = $this->getRequestStateFromEvent($event);

        $remoteRequest = $this->remoteRequestFactory->create($jobId, $remoteRequestType);
        $remoteRequest->setState($requestState);
        $this->remoteRequestRepository->save($remoteRequest);
    }

    private function getRequestStateFromEvent(object $event): RequestState
    {
        if ($event instanceof FailedEvent) {
            return $event->willRetry() ? RequestState::HALTED : RequestState::FAILED;
        }

        if ($event instanceof HandledEvent) {
            return RequestState::SUCCEEDED;
        }

        if ($event instanceof ReceivedEvent) {
            return RequestState::REQUESTING;
        }

        return RequestState::UNKNOWN;
    }
}
