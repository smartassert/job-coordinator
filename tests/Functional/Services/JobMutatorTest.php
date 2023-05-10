<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Repository\JobRepository;
use App\Services\JobMutator;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Ulid;

class JobMutatorTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private JobMutator $jobMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->jobRepository = $jobRepository;

        $jobMutator = self::getContainer()->get(JobMutator::class);
        \assert($jobMutator instanceof JobMutator);
        $this->jobMutator = $jobMutator;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->jobMutator);
        self::assertArrayHasKey(MachineIsActiveEvent::class, $this->jobMutator::getSubscribedEvents());
        self::assertArrayHasKey(MachineStateChangeEvent::class, $this->jobMutator::getSubscribedEvents());
        self::assertArrayHasKey(ResultsJobCreatedEvent::class, $this->jobMutator::getSubscribedEvents());
        self::assertArrayHasKey(SerializedSuiteCreatedEvent::class, $this->jobMutator::getSubscribedEvents());
    }

    public function testSetMachineIpAddressOnMachineIsActiveEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $jobId = md5((string) rand());

        $event = new MachineIsActiveEvent('authentication token', $jobId, '127.0.0.1');

        $this->jobMutator->setMachineIpAddressOnMachineIsActiveEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testSetMachineIpAddressOnMachineIsActiveEventIpAddressIsSet(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $this->jobRepository->add($job);
        self::assertNull($job->getMachineIpAddress());
        self::assertSame(1, $this->jobRepository->count([]));

        $ipAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $event = new MachineIsActiveEvent('authentication token', $jobId, $ipAddress);

        $this->jobMutator->setMachineIpAddressOnMachineIsActiveEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));
        self::assertSame($ipAddress, $job->getMachineIpAddress());
    }

    public function testSetMachineStateCategoryOnMachineStateChangeEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $event = new MachineStateChangeEvent(
            'authentication token',
            new Machine('machine id', 'unknown', 'unknown', []),
            new Machine('machine id', 'find/finding', 'finding', []),
        );

        $this->jobMutator->setMachineStateCategoryOnMachineStateChangeEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testSetMachineStateCategoryOnMachineStateChangeEventMachineStateIsSet(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $this->jobRepository->add($job);
        self::assertNull($job->getMachineStateCategory());
        self::assertSame(1, $this->jobRepository->count([]));

        $machineStateCategory = 'finding';

        $event = new MachineStateChangeEvent(
            'authentication token',
            new Machine($jobId, 'unknown', 'unknown', []),
            new Machine($jobId, 'find/finding', $machineStateCategory, []),
        );

        $this->jobMutator->setMachineStateCategoryOnMachineStateChangeEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));
        self::assertSame($machineStateCategory, $job->getMachineStateCategory());
    }

    public function testSetResultsJobOnResultsJobCreatedEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $resultsJob = new ResultsJob(md5((string) rand()), md5((string) rand()));
        $event = new ResultsJobCreatedEvent($resultsJob);

        $this->jobMutator->setResultsJobOnResultsJobCreatedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testSetResultsJobOnResultsJobCreatedEventSuccess(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $job->setResultsJobRequestState(RequestState::REQUESTING);
        self::assertNull($job->getResultsToken());
        self::assertSame(RequestState::REQUESTING, $job->getResultsJobRequestState());

        $this->jobRepository->add($job);
        self::assertSame(1, $this->jobRepository->count([]));

        $resultsJob = new ResultsJob($jobId, md5((string) rand()));
        $event = new ResultsJobCreatedEvent($resultsJob);

        $this->jobMutator->setResultsJobOnResultsJobCreatedEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));

        $retrievedJob = $this->jobRepository->find($jobId);
        self::assertInstanceOf(Job::class, $retrievedJob);

        self::assertSame($jobId, $retrievedJob->id);
        self::assertSame($resultsJob->token, $retrievedJob->getResultsToken());
        self::assertSame(RequestState::SUCCEEDED, $retrievedJob->getResultsJobRequestState());
    }

    public function testSetSerializedSuiteOnSerializedSuiteCreatedEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $event = new SerializedSuiteCreatedEvent(
            md5((string) rand()),
            md5((string) rand()),
            \Mockery::mock(SerializedSuite::class),
        );

        $this->jobMutator->setSerializedSuiteOnSerializedSuiteCreatedEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testSetSerializedSuiteOnSerializedSuiteCreatedEventSuccess(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 600);
        $job->setSerializedSuiteRequestState(RequestState::REQUESTING);
        self::assertNull($job->getSerializedSuiteId());
        self::assertSame(RequestState::REQUESTING, $job->getSerializedSuiteRequestState());

        $this->jobRepository->add($job);
        self::assertSame(1, $this->jobRepository->count([]));

        $serializedSuiteId = md5((string) rand());

        $serializedSuite = \Mockery::mock(SerializedSuite::class);
        $serializedSuite
            ->shouldReceive('getId')
            ->andReturn($serializedSuiteId)
        ;

        $event = new SerializedSuiteCreatedEvent(md5((string) rand()), $jobId, $serializedSuite);

        $this->jobMutator->setSerializedSuiteOnSerializedSuiteCreatedEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));

        $retrievedJob = $this->jobRepository->find($jobId);
        self::assertInstanceOf(Job::class, $retrievedJob);

        self::assertSame($serializedSuite->getId(), $job->getSerializedSuiteId());
        self::assertSame(RequestState::SUCCEEDED, $job->getSerializedSuiteRequestState());
    }
}
