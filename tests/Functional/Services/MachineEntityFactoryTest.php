<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Model\Machine\Machine as MachineModel;
use App\Model\MetaState;
use App\Repository\MachineRepository;
use App\Services\MachineEntityFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\MachineFactory;
use App\Tests\Services\Generator\Id;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MachineEntityFactoryTest extends WebTestCase
{
    private MachineRepository $machineRepository;
    private MachineEntityFactory $factory;

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

        $factory = self::getContainer()->get(MachineEntityFactory::class);
        \assert($factory instanceof MachineEntityFactory);
        $this->factory = $factory;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->factory::getSubscribedEvents();
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

        $jobId = Id::generate();

        $machine = MachineFactory::createRandomForJob($jobId);

        $event = new MachineCreationRequestedEvent($machine);

        $this->factory->createOnMachineCreationRequestedEvent($event);

        self::assertSame(0, $this->machineRepository->count([]));
    }

    /**
     * @param callable(string): MachineModel $machineCreator
     * @param callable(string): Machine      $expectedEntityCreator
     */
    #[DataProvider('createOnMachineRetrievedEventSuccessDataProvider')]
    public function testCreateOnMachineRetrievedEventSuccess(
        callable $machineCreator,
        callable $expectedEntityCreator
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        self::assertSame(0, $this->machineRepository->count([]));

        $machine = $machineCreator($job->getId());
        $event = new MachineCreationRequestedEvent($machine);

        $this->factory->createOnMachineCreationRequestedEvent($event);

        $machineEntity = $this->machineRepository->find($job->getId());
        self::assertEquals($expectedEntityCreator($job->getId()), $machineEntity);
    }

    /**
     * @return array<mixed>
     */
    public static function createOnMachineRetrievedEventSuccessDataProvider(): array
    {
        return [
            'without failed state' => [
                'machineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new MachineModel(
                        $jobId,
                        'find/finding',
                        'find',
                        null,
                        null,
                        new MetaState(false, false, true),
                        false,
                        false,
                        false,
                    );
                },
                'expectedEntityCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new Machine($jobId, 'find/finding', 'find');
                },
            ],
            'with failed state' => [
                'machineCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new MachineModel(
                        $jobId,
                        'find/not-findable',
                        'end',
                        null,
                        null,
                        new MetaState(true, false, false),
                        false,
                        false,
                        false,
                    );
                },
                'expectedEntityCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return new Machine($jobId, 'find/not-findable', 'end');
                },
            ],
        ];
    }
}
