<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteMutator implements EventSubscriberInterface
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
            SerializedSuiteRetrievedEvent::class => [
                ['setState', 1000],
            ],
        ];
    }

    public function setState(SerializedSuiteRetrievedEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->getId());
        if (!$serializedSuite instanceof SerializedSuite) {
            return;
        }

        $serializedSuite->setState($event->serializedSuite->getState());
        $serializedSuite->setIsPrepared($event->serializedSuite->isPrepared());
        $serializedSuite->setHasEndState($event->serializedSuite->hasEndState());
        $this->serializedSuiteRepository->save($serializedSuite);
    }
}
