<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteCreatedEvent;
use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SerializedSuiteCreatedEvent::class => [
                ['createOnSerializedSuiteCreatedEvent', 1000],
            ],
        ];
    }

    public function createOnSerializedSuiteCreatedEvent(SerializedSuiteCreatedEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        if (!$this->serializedSuiteRepository->has($job->getId())) {
            $this->serializedSuiteRepository->save(
                new SerializedSuite(
                    $job->getId(),
                    $event->serializedSuite->getId(),
                    $event->serializedSuite->getState(),
                    $event->serializedSuite->isPrepared(),
                    $event->serializedSuite->hasEndState(),
                    new MetaState(
                        $event->serializedSuite->getMetaState()->ended,
                        $event->serializedSuite->getMetaState()->succeeded,
                    )
                )
            );
        }
    }
}
