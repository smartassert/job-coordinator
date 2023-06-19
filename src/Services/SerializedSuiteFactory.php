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
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $this->create($job, $event->serializedSuite->getId(), $event->serializedSuite->getState());
    }

    /**
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function create(Job $job, string $serializedSuiteId, string $state): SerializedSuite
    {
        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuite) {
            $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, $state);
            $this->serializedSuiteRepository->save($serializedSuite);
        }

        return $serializedSuite;
    }
}
