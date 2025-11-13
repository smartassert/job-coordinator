<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Repository\MachineRepository;
use App\Services\MachineFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as WorkerMachineFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerClientMachine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class MachineFactoryTest extends WebTestCase
{
    private MachineRepository $machineRepository;
    private MachineFactory $machineFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        foreach ($machineRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $this->machineRepository = $machineRepository;

        $machineFactory = self::getContainer()->get(MachineFactory::class);
        \assert($machineFactory instanceof MachineFactory);
        $this->machineFactory = $machineFactory;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->machineFactory::getSubscribedEvents();
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
            MachineCreationRequestedEvent::class => [
                'expectedListenedForEvent' => MachineCreationRequestedEvent::class,
                'expectedMethod' => 'createOnMachineCreationRequestedEvent',
            ],
        ];
    }

    public function testCreateOnMachineRetrievedEventNoJob(): void
    {
        self::assertSame(0, $this->machineRepository->count([]));

        $jobId = (string) new Ulid();

        $machine = WorkerMachineFactory::createRandomForJob($jobId);

        $event = new MachineCreationRequestedEvent('authentication token', $machine);

        $this->machineFactory->createOnMachineCreationRequestedEvent($event);

        self::assertSame(0, $this->machineRepository->count([]));
    }

    /**
     * @param callable(string): WorkerManagerClientMachine $workerManagerClientMachineCreator
     * @param callable(string): Machine                    $expectedMachineCreator
     */
    #[DataProvider('createOnMachineRetrievedEventSuccessDataProvider')]
    public function testCreateOnMachineRetrievedEventSuccess(
        callable $workerManagerClientMachineCreator,
        callable $expectedMachineCreator
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        self::assertSame(0, $this->machineRepository->count([]));

        $machine = $workerManagerClientMachineCreator($job->getId());

        $event = new MachineCreationRequestedEvent('authentication token', $machine);

        $this->machineFactory->createOnMachineCreationRequestedEvent($event);

        $machineEntity = $this->machineRepository->find($job->getId());
        self::assertEquals($expectedMachineCreator($job->getId()), $machineEntity);
    }

    /**
     * @return array<mixed>
     */
    public static function createOnMachineRetrievedEventSuccessDataProvider(): array
    {
        return [
            'without failed state' => [
                'workerManagerClientMachineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return WorkerMachineFactory::create(
                        $jobId,
                        'find/finding',
                        'find',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'expectedMachineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new Machine(
                        $jobId,
                        'find/finding',
                        'find',
                        false,
                        false,
                    );
                },
            ],
            'with failed state' => [
                'workerManagerClientMachineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return WorkerMachineFactory::create(
                        $jobId,
                        'find/not-findable',
                        'end',
                        [],
                        true,
                        false,
                        false,
                        true,
                    );
                },
                'expectedMachineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new Machine(
                        $jobId,
                        'find/not-findable',
                        'end',
                        true,
                        true,
                    );
                },
            ],
        ];
    }
}
