<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Entity\MachineActionFailure;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\JobInterface;
use App\Repository\MachineRepository;
use App\Services\MachineMutator;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class MachineMutatorTest extends WebTestCase
{
    private MachineRepository $machineRepository;
    private MachineMutator $machineMutator;
    private JobFactory $jobFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->jobFactory = $jobFactory;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

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

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->machineMutator::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
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
            MachineHasActionFailureEvent::class => [
                'expectedListenedForEvent' => MachineHasActionFailureEvent::class,
                'expectedMethod' => 'setActionFailureOnMachineHasActionFailureEvent',
            ],
        ];
    }

    /**
     * @param callable(JobFactory): ?JobInterface              $jobCreator
     * @param callable(?JobInterface, MachineRepository): void $machineCreator
     * @param callable(?JobInterface): MachineStateChangeEvent $eventCreator
     * @param callable(?JobInterface): ?Machine                $expectedMachineCreator
     */
    #[DataProvider('setStateOnMachineStateChangeEventDataProvider')]
    public function testSetStateOnMachineStateChangeEvent(
        callable $jobCreator,
        callable $machineCreator,
        callable $eventCreator,
        callable $expectedMachineCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);

        $machineCreator($job, $this->machineRepository);

        $event = $eventCreator($job);

        $this->machineMutator->setStateOnMachineStateChangeEvent($event);

        $machine = null === $job
            ? null
            : $this->machineRepository->find($job->getId());

        self::assertEquals($expectedMachineCreator($job), $machine);
    }

    /**
     * @return array<mixed>
     */
    public static function setStateOnMachineStateChangeEventDataProvider(): array
    {
        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new MachineStateChangeEvent(
                        WorkerManagerClientMachineFactory::createRandomForJob($jobId),
                        WorkerManagerClientMachineFactory::createRandomForJob($jobId)
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
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new MachineStateChangeEvent(
                        WorkerManagerClientMachineFactory::createRandomForJob($jobId),
                        WorkerManagerClientMachineFactory::createRandomForJob($jobId)
                    );
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no state change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->getId(), 'up/started', 'pre_active', false);
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineStateChangeEvent(
                        WorkerManagerClientMachineFactory::createRandomForJob($job->getId()),
                        WorkerManagerClientMachineFactory::create(
                            $job->getId(),
                            'up/started',
                            'pre_active',
                            [],
                            false,
                            false,
                            false,
                            false,
                        )
                    );
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return new Machine($job->getId(), 'up/started', 'pre_active', false);
                },
            ],
            'has state change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->getId(), 'up/started', 'pre_active', false);
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineStateChangeEvent(
                        WorkerManagerClientMachineFactory::createRandomForJob($job->getId()),
                        WorkerManagerClientMachineFactory::create(
                            $job->getId(),
                            'up/active',
                            'active',
                            [],
                            false,
                            true,
                            false,
                            false,
                        )
                    );
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return new Machine($job->getId(), 'up/active', 'active', false);
                },
            ],
        ];
    }

    /**
     * @param callable(JobFactory): ?JobInterface              $jobCreator
     * @param callable(?JobInterface, MachineRepository): void $machineCreator
     * @param callable(?JobInterface): MachineIsActiveEvent    $eventCreator
     * @param callable(?JobInterface): ?Machine                $expectedMachineCreator
     */
    #[DataProvider('setIpOnMachineIsActiveEventDataProvider')]
    public function testSetIpOnMachineIsActiveEvent(
        callable $jobCreator,
        callable $machineCreator,
        callable $eventCreator,
        callable $expectedMachineCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);
        $machineCreator($job, $this->machineRepository);

        $event = $eventCreator($job);

        $this->machineMutator->setIpOnMachineIsActiveEvent($event);

        $machine = null === $job
            ? null
            : $this->machineRepository->find($job->getId());

        self::assertEquals($expectedMachineCreator($job), $machine);
    }

    /**
     * @return array<mixed>
     */
    public static function setIpOnMachineIsActiveEventDataProvider(): array
    {
        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new MachineIsActiveEvent(md5((string) rand()), $jobId, '127.0.0.1');
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no machine' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function () {
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->getId(), '127.0.0.1');
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'no ip change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = (new Machine($job->getId(), 'up/started', 'pre_active', false))
                        ->setIp('127.0.0.1')
                    ;
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->getId(), '127.0.0.1');
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return (new Machine($job->getId(), 'up/started', 'pre_active', false))
                        ->setIp('127.0.0.1')
                    ;
                },
            ],
            'has ip change, null to set' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->getId(), 'up/started', 'pre_active', false);
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->getId(), '127.0.0.1');
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return (new Machine($job->getId(), 'up/started', 'pre_active', false))
                        ->setIp('127.0.0.1')
                    ;
                },
            ],
            'has ip change' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = (new Machine($job->getId(), 'up/started', 'pre_active', false))
                        ->setIp('127.0.0.1')
                    ;
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineIsActiveEvent(md5((string) rand()), $job->getId(), '127.0.0.2');
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return (new Machine($job->getId(), 'up/started', 'pre_active', false))
                        ->setIp('127.0.0.2')
                    ;
                },
            ],
        ];
    }

    /**
     * @param callable(JobFactory): ?JobInterface                   $jobCreator
     * @param callable(?JobInterface, MachineRepository): void      $machineCreator
     * @param callable(?JobInterface): MachineHasActionFailureEvent $eventCreator
     * @param callable(?JobInterface): ?Machine                     $expectedMachineCreator
     */
    #[DataProvider('setActionFailureOnMachineHasActionFailureEventDataProvider')]
    public function testSetActionFailureOnMachineHasActionFailureEvent(
        callable $jobCreator,
        callable $machineCreator,
        callable $eventCreator,
        callable $expectedMachineCreator,
    ): void {
        $job = $jobCreator($this->jobFactory);
        $machineCreator($job, $this->machineRepository);

        $event = $eventCreator($job);

        $this->machineMutator->setActionFailureOnMachineHasActionFailureEvent($event);

        $machine = null === $job
            ? null
            : $this->machineRepository->find($job->getId());

        self::assertEquals($expectedMachineCreator($job), $machine);
    }

    /**
     * @return array<mixed>
     */
    public static function setActionFailureOnMachineHasActionFailureEventDataProvider(): array
    {
        $jobCreator = function (JobFactory $jobFactory) {
            return $jobFactory->createRandom();
        };

        return [
            'no job' => [
                'jobCreator' => function () {
                    return null;
                },
                'machineCreator' => function () {
                },
                'eventCreator' => function () {
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new MachineHasActionFailureEvent(
                        $jobId,
                        new ActionFailure('find', 'vendor_authentication_failure', [])
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
                    $jobId = (string) new Ulid();
                    \assert('' !== $jobId);

                    return new MachineHasActionFailureEvent(
                        $jobId,
                        new ActionFailure('find', 'vendor_authentication_failure', [])
                    );
                },
                'expectedMachineCreator' => function () {
                    return null;
                },
            ],
            'previous machine has no action failure' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = new Machine($job->getId(), 'find/finding', 'pre_active', false);
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineHasActionFailureEvent(
                        $job->getId(),
                        new ActionFailure('find', 'vendor_authentication_failure', [])
                    );
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return (new Machine($job->getId(), 'find/finding', 'pre_active', false))
                        ->setActionFailure(
                            new MachineActionFailure($job->getId(), 'find', 'vendor_authentication_failure', [])
                        )
                    ;
                },
            ],
            'previous machine has action failure' => [
                'jobCreator' => $jobCreator,
                'machineCreator' => function (JobInterface $job, MachineRepository $machineRepository) {
                    $machine = (new Machine($job->getId(), 'find/finding', 'pre_active', false))
                        ->setActionFailure(
                            new MachineActionFailure($job->getId(), 'previous_action', 'previous_type', [])
                        )
                    ;
                    $machineRepository->save($machine);

                    return $machine;
                },
                'eventCreator' => function (JobInterface $job) {
                    return new MachineHasActionFailureEvent(
                        $job->getId(),
                        new ActionFailure('new_action', 'new_type', [])
                    );
                },
                'expectedMachineCreator' => function (JobInterface $job) {
                    return (new Machine($job->getId(), 'find/finding', 'pre_active', false))
                        ->setActionFailure(
                            new MachineActionFailure($job->getId(), 'previous_action', 'previous_type', [])
                        )
                    ;
                },
            ],
        ];
    }
}
