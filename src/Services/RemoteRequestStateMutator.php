<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RequestState;
use App\Event\RemoteRequestCancelledEvent;
use App\Event\RemoteRequestCompletedEvent;
use App\Event\RemoteRequestEventInterface;
use App\Event\RemoteRequestFailedEvent;
use App\Event\RemoteRequestStartedEvent;
use App\Repository\RemoteRequestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestStateMutator implements EventSubscriberInterface
{
    private const EVENT_REQUEST_STATE_MAP = [
        RemoteRequestCancelledEvent::class => RequestState::FAILED,
        RemoteRequestCompletedEvent::class => RequestState::SUCCEEDED,
        RemoteRequestFailedEvent::class => RequestState::HALTED,
        RemoteRequestStartedEvent::class => RequestState::REQUESTING,
    ];

    public function __construct(
        private readonly RemoteRequestRepository $repository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RemoteRequestCancelledEvent::class => [
                ['setRemoteRequestState', 1000],
            ],
            RemoteRequestCompletedEvent::class => [
                ['setRemoteRequestState', 1000],
            ],
            RemoteRequestFailedEvent::class => [
                ['setRemoteRequestState', 1000],
            ],
            RemoteRequestStartedEvent::class => [
                ['setRemoteRequestState', 1000],
            ],
        ];
    }

    public function setRemoteRequestState(RemoteRequestEventInterface $event): void
    {
        $remoteRequest = $event->getRemoteRequest();
        $remoteRequest->setState(self::EVENT_REQUEST_STATE_MAP[$event::class] ?? RequestState::UNKNOWN);
        $this->repository->save($remoteRequest);
    }
}
