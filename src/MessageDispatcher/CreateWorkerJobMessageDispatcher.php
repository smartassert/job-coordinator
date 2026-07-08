<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsReadyEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
        private JobRepository $jobRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsReadyEvent::class => [
                ['dispatchImmediately', 100],
            ],
            MessageNotHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    public function dispatchImmediately(MachineIsReadyEvent $event): void
    {
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
        if (null === $job) {
            return;
        }

        $message = new CreateWorkerJobMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $job->getMaximumDurationInSeconds(),
            $event->ipAddress
        );

        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }

    public function redispatch(MessageNotHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof CreateWorkerJobMessage) {
            return;
        }

        $readiness = $this->readinessAssessor->isReady($message->getJobId());

        if (MessageHandlingReadiness::NEVER === $event->readiness || MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
