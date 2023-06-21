<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Services\MachineFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerMachine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineFactoryTest extends WebTestCase
{
    private JobRepository $jobRepository;
    private MachineRepository $machineRepository;
    private MachineFactory $machineFactory;

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

        $machineFactory = self::getContainer()->get(MachineFactory::class);
        \assert($machineFactory instanceof MachineFactory);
        $this->machineFactory = $machineFactory;
    }

    public function testIsEventSubscriber(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->machineFactory);
    }

    /**
     * @dataProvider eventSubscriptionsDataProvider
     */
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->machineFactory::getSubscribedEvents();
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
            MachineCreationRequestedEvent::class => [
                'expectedListenedForEvent' => MachineCreationRequestedEvent::class,
                'expectedMethod' => 'createOnMachineCreationRequestedEvent',
            ],
        ];
    }

    public function testCreateOnMachineRetrievedEventNoJob(): void
    {
        self::assertSame(0, $this->machineRepository->count([]));

        $machine = new WorkerManagerMachine(md5((string) rand()), md5((string) rand()), md5((string) rand()), []);

        $event = new MachineCreationRequestedEvent('authentication token', $machine);

        $this->machineFactory->createOnMachineCreationRequestedEvent($event);

        self::assertSame(0, $this->machineRepository->count([]));
    }

    public function testCreateOnMachineRetrievedEventSuccess(): void
    {
        $job = new Job(md5((string) rand()), 'user id', 'suite id', 600);
        $this->jobRepository->add($job);

        self::assertSame(0, $this->machineRepository->count([]));

        $machine = new WorkerManagerMachine($job->id, md5((string) rand()), md5((string) rand()), []);

        $event = new MachineCreationRequestedEvent('authentication token', $machine);

        $this->machineFactory->createOnMachineCreationRequestedEvent($event);

        $machineEntity = $this->machineRepository->find($job->id);
        self::assertEquals(new Machine($job->id, $machine->state, $machine->stateCategory), $machineEntity);
    }
}
