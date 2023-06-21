<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Services\MachineMutator;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\WorkerManagerClient\Model\Machine as MachineModel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineMutatorTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private MachineRepository $machineRepository;
    private MachineMutator $machineMutator;

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

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        foreach ($machineRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->machineRepository = $machineRepository;

        $machineMutator = self::getContainer()->get(MachineMutator::class);
        \assert($machineMutator instanceof MachineMutator);
        $this->machineMutator = $machineMutator;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->machineMutator);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->machineMutator::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions);
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public function eventSubscriptionsDataProvider(): array
    {
        return [
            MachineStateChangeEvent::class => [
                'expectedListenedForEvent' => MachineStateChangeEvent::class,
                'expectedMethod' => 'setStateOnMachineStateChangeEvent',
            ],
            MachineIsActiveEvent::class => [
                'expectedListenedForEvent' => MachineIsActiveEvent::class,
                'expectedMethod' => 'setIpOnMachineIsActiveEvent',
            ],
        ];
    }

    /**
     * @dataProvider setStateOnMachineStateChangeEventDataProvider
     *
     * @param callable(non-empty-string, JobRepository): ?Job $jobCreator
     * @param callable(?Job, MachineRepository): void         $machineCreator
     * @param callable(?Job): MachineStateChangeEvent         $eventCreator
     * @param callable(?Job): ?Machine                        $expectedMachineCreator
     */
    public function testSetStateOnMachineStateChangeEvent(
        callable $jobCreator,
        callable $machineCreator,
        callable $eventCreator,
        callable $expectedMachineCreator,
    ): void {
        $jobId = md5((string) rand());

        $job = $jobCreator($jobId, $this->jobRepository);
        $machineCreator($job, $this->machineRepository);

        $event = $eventCreator($job);

        $this->machineMutator->setStateOnMachineStateChangeEvent($event);

        $machine = $this->machineRepository->find($jobId);

        self::assertEquals($expectedMachineCreator($job), $machine);
    }

    /**
     * @return array<mixed>
     */
    public function setStateOnMachineStateChangeEventDataProvider(): array
    {
        $jobCreator = function (string $jobId, JobRepository $jobRepository) {
            \assert('' !== $jobId);

            $job = new Job($jobId, 'user id', 'suite id', 600);
            $jobRepository->add($job);

            return $job;
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    return new MachineStateChangeEvent(
                        md5((string) rand()),
                        \Mockery::mock(MachineModel::class),
                        new MachineModel(md5((string) rand()), md5((string) rand()), md5((string) rand()), [])
                    );
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no machine' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    return new MachineStateChangeEvent(
                        md5((string) rand()),
                        \Mockery::mock(MachineModel::class),
                        new MachineModel(md5((string) rand()), md5((string) rand()), md5((string) rand()), [])
                    );
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, 'up/started', 'pre_active');
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (Job $job) {
                    return new MachineStateChangeEvent(
                        md5((string) rand()),
                        \Mockery::mock(MachineModel::class),
                        new MachineModel($job->id, 'up/started', 'pre_active', [])
                    );
                },
                'expectedMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'up/started', 'pre_active');
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, 'up/started', 'pre_active');
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (Job $job) {
                    return new MachineStateChangeEvent(
                        md5((string) rand()),
                        \Mockery::mock(MachineModel::class),
                        new MachineModel($job->id, 'up/active', 'active', [])
                    );
                },
                'expectedMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'up/active', 'active');
                },
            ],
        ];
    }

    /**
     * @dataProvider setIpOnMachineIsActiveEventDataProvider
     *
     * @param callable(non-empty-string, JobRepository): ?Job $jobCreator
     * @param callable(?Job, MachineRepository): void         $machineCreator
     * @param callable(?Job): MachineIsActiveEvent            $eventCreator
     * @param callable(?Job): ?Machine                        $expectedMachineCreator
     */
    public function testSetIpOnMachineIsActiveEvent(
        callable $jobCreator,
        callable $machineCreator,
        callable $eventCreator,
        callable $expectedMachineCreator,
    ): void {
        $jobId = md5((string) rand());

        $job = $jobCreator($jobId, $this->jobRepository);
        $machineCreator($job, $this->machineRepository);

        $event = $eventCreator($job);

        $this->machineMutator->setIpOnMachineIsActiveEvent($event);

        $machine = $this->machineRepository->find($jobId);

        self::assertEquals($expectedMachineCreator($job), $machine);
    }

    /**
     * @return array<mixed>
     */
    public function setIpOnMachineIsActiveEventDataProvider(): array
    {
        $jobCreator = function (string $jobId, JobRepository $jobRepository) {
            \assert('' !== $jobId);

            $job = new Job($jobId, 'user id', 'suite id', 600);
            $jobRepository->add($job);

            return $job;
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    return new MachineIsActiveEvent(md5((string) rand()), md5((string) rand()), '127.0.0.1');
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no machine' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function () {
                },
                'eventCreator' => function (Job $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->id, '127.0.0.1');
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no ip change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = (new Machine($job->id, 'up/started', 'pre_active'))
                        ->setIp('127.0.0.1')
                    ;
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (Job $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->id, '127.0.0.1');
                },
                'expectedMachineCreator' => function (Job $job) {
                    return (new Machine($job->id, 'up/started', 'pre_active'))
                        ->setIp('127.0.0.1')
                    ;
                },
            ],
            'has ip change, null to set' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->id, 'up/started', 'pre_active');
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (Job $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->id, '127.0.0.1');
                },
                'expectedMachineCreator' => function (Job $job) {
                    return (new Machine($job->id, 'up/started', 'pre_active'))
                        ->setIp('127.0.0.1')
                    ;
                },
            ],
            'has ip change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (Job $job, MachineRepository $machineRepository) {
                    $machine = (new Machine($job->id, 'up/started', 'pre_active'))
                        ->setIp('127.0.0.1')
                    ;
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (Job $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->id, '127.0.0.2');
                },
                'expectedMachineCreator' => function (Job $job) {
                    return (new Machine($job->id, 'up/started', 'pre_active'))
                        ->setIp('127.0.0.2')
                    ;
                },
            ],
        ];
    }
}
