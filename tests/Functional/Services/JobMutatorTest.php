<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Repository\JobRepository;
use App\Services\JobMutator;
use Doctrine\ORM\EntityManagerInterface;
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
    }

    public function testSetMachineIpAddressOnMachineIsActiveEventNoJob(): void
    {
        self::assertSame(0, $this->jobRepository->count([]));

        $machineId = md5((string) rand());

        $event = new MachineIsActiveEvent(
            'authentication token',
            new Machine($machineId, 'find/received', 'finding', ['127.0.0.1']),
            new Machine($machineId, 'up/active', 'active', ['127.0.0.1']),
            '127.0.0.1',
        );

        $this->jobMutator->setMachineIpAddressOnMachineIsActiveEvent($event);

        self::assertSame(0, $this->jobRepository->count([]));
    }

    public function testSetMachineIpAddressOnMachineIsActiveEventNoMachineIpAddress(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 'results token', 'serialized suite id', 600);
        $this->jobRepository->add($job);
        self::assertNull($job->getMachineIpAddress());
        self::assertSame(1, $this->jobRepository->count([]));

        $machineId = md5((string) rand());

        $event = new MachineIsActiveEvent(
            'authentication token',
            new Machine($machineId, 'find/received', 'finding', ['127.0.0.1']),
            new Machine($machineId, 'up/active', 'active', ['127.0.0.1']),
            '127.0.0.1',
        );

        $this->jobMutator->setMachineIpAddressOnMachineIsActiveEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));
        self::assertNull($job->getMachineIpAddress());
    }

    public function testSetMachineIpAddressOnMachineIsActiveEventIpAddressIsSet(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $job = new Job($jobId, 'user id', 'suite id', 'results token', 'serialized suite id', 600);
        $this->jobRepository->add($job);
        self::assertNull($job->getMachineIpAddress());
        self::assertSame(1, $this->jobRepository->count([]));

        $ipAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $event = new MachineIsActiveEvent(
            'authentication token',
            new Machine($jobId, 'find/received', 'finding', []),
            new Machine($jobId, 'up/active', 'active', [$ipAddress]),
            $ipAddress
        );

        $this->jobMutator->setMachineIpAddressOnMachineIsActiveEvent($event);

        self::assertSame(1, $this->jobRepository->count([]));
        self::assertSame($ipAddress, $job->getMachineIpAddress());
    }
}
