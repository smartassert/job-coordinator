<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

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
        $job = $this->jobRepository->find($event->getJobId());
        if (!$job instanceof Job) {
            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($event->getJobId());
        if (null === $serializedSuite) {
            $serializedSuite = new SerializedSuite(
                $event->getJobId(),
                $event->serializedSuite->getId(),
                $event->serializedSuite->getState(),
                $event->serializedSuite->isPrepared(),
                $event->serializedSuite->hasEndState(),
            );
            $this->serializedSuiteRepository->save($serializedSuite);
        }
    }
}
