<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\RemoteRequestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class RemoteRequestStateTracker implements EventSubscriberInterface
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestIndexGenerator $remoteRequestIndexGenerator,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => [
                ['setRemoteRequestStateForMessageFailedEvent', 10000],
            ],
            WorkerMessageHandledEvent::class => [
                ['setRemoteRequestStateForMessageHandledEvent', 10000],
            ],
            WorkerMessageReceivedEvent::class => [
                ['setRemoteRequestStateForMessageReceivedEvent', 10000],
            ],
            JobRemoteRequestMessageCreatedEvent::class => [
                ['setRemoteRequestStateForJobRemoteRequestMessageCreatedEvent', 10000],
            ],
            MessageNotYetHandleableEvent::class => [
                ['setRemoteRequestStateForMessageNotYetHandleableEvent', 10000],
            ],
            MessageNotHandleableEvent::class => [
                ['setRemoteRequestStateForMessageNotHandleableEvent', 10000],
            ],
        ];
    }

    public function setRemoteRequestStateForMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        $this->setRemoteRequestForMessage($message, $event->willRetry() ? RequestState::HALTED : RequestState::FAILED);
    }

    public function setRemoteRequestStateForMessageHandledEvent(WorkerMessageHandledEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        $this->setRemoteRequestForMessage($message, RequestState::SUCCEEDED);
    }

    public function setRemoteRequestStateForMessageReceivedEvent(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        $this->setRemoteRequestForMessage($message, RequestState::REQUESTING);
    }

    public function setRemoteRequestStateForJobRemoteRequestMessageCreatedEvent(
        JobRemoteRequestMessageCreatedEvent $event
    ): void {
        $message = $event->message;
        $message = $message->setIndex(
            $this->remoteRequestIndexGenerator->generate($message->getJobId(), $message->getRemoteRequestType())
        );

        $this->setRemoteRequestForMessage($message, RequestState::REQUESTING);
    }

    public function setRemoteRequestStateForMessageNotYetHandleableEvent(MessageNotYetHandleableEvent $event): void
    {
        $this->setRemoteRequestForMessage($event->message, RequestState::HALTED);
    }

    public function setRemoteRequestStateForMessageNotHandleableEvent(MessageNotHandleableEvent $event): void
    {
        $this->setRemoteRequestForMessage($event->message, RequestState::ABORTED);
    }

    private function setRemoteRequestForMessage(
        JobRemoteRequestMessageInterface $message,
        RequestState $requestState
    ): void {
        $jobId = $message->getJobId();

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($jobId, $message->getRemoteRequestType(), $message->getIndex())
        );

        if ($remoteRequest instanceof RemoteRequest) {
            if ($requestState === $remoteRequest->getState()) {
                return;
            }
        } else {
            $remoteRequest = new RemoteRequest($jobId, $message->getRemoteRequestType(), $message->getIndex());
        }

        if ($remoteRequest->hasEndState()) {
            return;
        }

        $remoteRequest->setState($requestState);
        $this->remoteRequestRepository->save($remoteRequest);
    }
}
