<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
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
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FailedEvent::class => [
                ['setRemoteRequestStateForMessengerEvent', 10000],
            ],
            HandledEvent::class => [
                ['setRemoteRequestStateForMessengerEvent', 10000],
            ],
            ReceivedEvent::class => [
                ['setRemoteRequestStateForMessengerEvent', 10000],
            ],
            JobRemoteRequestMessageCreatedEvent::class => [
                ['setRemoteRequestStateForJobRemoteRequestMessageCreatedEvent', 10000],
            ],
        ];
    }

    public function setRemoteRequestStateForMessengerEvent(FailedEvent|HandledEvent|ReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        $this->setRemoteRequestForMessage($message, $this->getRequestStateFromEvent($event));
    }

    public function setRemoteRequestStateForJobRemoteRequestMessageCreatedEvent(
        JobRemoteRequestMessageCreatedEvent $event
    ): void {
        $this->setRemoteRequestForMessage($event->message, RequestState::UNKNOWN);
    }

    private function setRemoteRequestForMessage(
        JobRemoteRequestMessageInterface $message,
        RequestState $requestState
    ): void {
        $remoteRequest = $this->createRemoteRequest($message);
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

    private function createRemoteRequest(JobRemoteRequestMessageInterface $message): RemoteRequest
    {
        $jobId = $message->getJobId();
        $type = $message->getRemoteRequestType();

        $remoteRequest = $this->remoteRequestRepository->find(RemoteRequest::generateId($jobId, $type));

        if (null === $remoteRequest) {
            $remoteRequest = new RemoteRequest($jobId, $type);
            $this->remoteRequestRepository->save($remoteRequest);
        }

        return $remoteRequest;
    }
}
