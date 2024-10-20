<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteMutator implements EventSubscriberInterface
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
            SerializedSuiteRetrievedEvent::class => [
                ['setState', 1000],
            ],
        ];
    }

    public function setState(SerializedSuiteRetrievedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if (!$serializedSuite instanceof SerializedSuite) {
            return;
        }

        $serializedSuite->setState($event->serializedSuite->getState());
        $serializedSuite->setIsPrepared($event->serializedSuite->isPrepared());
        $serializedSuite->setHasEndState($event->serializedSuite->hasEndState());
        $this->serializedSuiteRepository->save($serializedSuite);
    }
}
