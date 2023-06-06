<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRequestedEvent;
use App\Event\MachineStateChangeEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JobMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['setMachineIpAddressOnMachineIsActiveEvent', 1000],
            ],
            MachineStateChangeEvent::class => [
                ['setMachineStateCategoryOnMachineStateChangeEvent', 1000],
            ],
            ResultsJobCreatedEvent::class => [
                ['setResultsJobOnResultsJobCreatedEvent', 1000],
            ],
            SerializedSuiteCreatedEvent::class => [
                ['setSerializedSuiteOnSerializedSuiteCreatedEvent', 1000],
            ],
            MachineRequestedEvent::class => [
                ['setMachineOnMachineRequestedEvent', 1000],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['setSerializedSuiteStateOnSerializedSuiteRetrievedEvent', 1000],
            ],
        ];
    }

    public function setMachineIpAddressOnMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $job->setMachineIpAddress($event->ipAddress);
        $this->jobRepository->add($job);
    }

    public function setMachineStateCategoryOnMachineStateChangeEvent(MachineStateChangeEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job) {
            return;
        }

        $machineStateCategory = $machine->stateCategory;
        if ('' === $machineStateCategory) {
            return;
        }

        $job->setMachineStateCategory($machineStateCategory);
        $this->jobRepository->add($job);
    }

    public function setResultsJobOnResultsJobCreatedEvent(ResultsJobCreatedEvent $event): void
    {
        $job = $this->jobRepository->find($event->resultsJob->label);
        if (!$job instanceof Job) {
            return;
        }

        $job = $job->setResultsToken($event->resultsJob->token);
        $this->jobRepository->add($job);
    }

    public function setSerializedSuiteOnSerializedSuiteCreatedEvent(SerializedSuiteCreatedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $job = $job->setSerializedSuiteRequestState(null);
        $job->setSerializedSuiteId($event->serializedSuite->getId());

        $this->jobRepository->add($job);
    }

    public function setMachineOnMachineRequestedEvent(MachineRequestedEvent $event): void
    {
        $machine = $event->machine;
        $jobId = $machine->id;

        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            return;
        }

        $job = $job->setMachineRequestState(null);
        if ('' !== $machine->stateCategory) {
            $job = $job->setMachineStateCategory($machine->stateCategory);
        }

        $this->jobRepository->add($job);
    }

    public function setSerializedSuiteStateOnSerializedSuiteRetrievedEvent(SerializedSuiteRetrievedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $serializedSuiteState = $event->serializedSuite->getState();
        if ('' === $serializedSuiteState || $serializedSuiteState === $job->getSerializedSuiteState()) {
            return;
        }

        $job->setSerializedSuiteState($serializedSuiteState);
        $this->jobRepository->add($job);
    }
}
