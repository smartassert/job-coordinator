<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventDispatcher;

use App\Entity\Machine as MachineEntity;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\Machine\ActionFailure;
use App\Model\Machine\Machine;
use App\Model\Machine\MachineInterface;
use App\Model\MetaState;
use App\Repository\MachineRepository;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Generator\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MachineStateEventDispatcherTest extends WebTestCase
{
    private EventRecorder $eventRecorder;
    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeAllMachines();
        $entityRemover->removeAllMachineActionFailures();
    }

    public function testDispatchMachineStateChangeEventNoStateChange(): void
    {
        $machineId = Id::generate();

        $machineModel = new Machine(
            $machineId,
            'unchanged-state',
            'active',
            null,
            null,
            new MetaState(false, false, false),
            true,
            false,
            false,
        );

        $this->persistMachineModel($machineModel);

        $event = new MachineRetrievedEvent($machineModel);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineStateChangeEvent::class));
    }

    public function testDispatchMachineStateChangeEventHasStateChange(): void
    {
        $machineId = Id::generate();

        $previousMachineModel = new Machine(
            $machineId,
            'previous-state',
            'active',
            null,
            null,
            new MetaState(false, false, false),
            true,
            false,
            false,
        );

        $this->persistMachineModel($previousMachineModel);

        $currentMachineModel = new Machine(
            $machineId,
            'current-state',
            'active',
            null,
            null,
            new MetaState(false, false, false),
            true,
            false,
            false,
        );

        $event = new MachineRetrievedEvent($currentMachineModel);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals(
            [
                new MachineStateChangeEvent($currentMachineModel),
            ],
            $this->eventRecorder->all(MachineStateChangeEvent::class)
        );
    }

    #[DataProvider('dispatchMachineIsActiveEventNotActiveDataProvider')]
    public function testDispatchMachineIsActiveEventMachineNotActive(
        MachineEntity $currentMachine,
        MachineRetrievedEvent $event,
    ): void {
        $this->machineRepository->save($currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineIsActiveEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchMachineIsActiveEventNotActiveDataProvider(): array
    {
        $machineId = Id::generate();

        $unknownMachine = new Machine(
            $machineId,
            'unknown',
            'unknown',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $unknownMachineEntity = self::createMachineEntityFromMachine($unknownMachine);

        $findingMachine = new Machine(
            $machineId,
            'find/finding',
            'pre_active',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $findingMachineEntity = self::createMachineEntityFromMachine($findingMachine);

        return [
            'unknown -> unknown' => [
                'currentMachine' => $unknownMachineEntity,
                'event' => new MachineRetrievedEvent($unknownMachine),
            ],
            'unknown -> finding' => [
                'currentMachine' => $findingMachineEntity,
                'event' => new MachineRetrievedEvent($findingMachine),
            ],
            'finding -> finding' => [
                'currentMachine' => $findingMachineEntity,
                'event' => new MachineRetrievedEvent($findingMachine),
            ],
            'finding -> active without ip address' => [
                'currentMachine' => $findingMachineEntity,
                'event' => new MachineRetrievedEvent(
                    new Machine(
                        $machineId,
                        'up/active',
                        'active',
                        null,
                        null,
                        new MetaState(false, false, false),
                        true,
                        false,
                        false,
                    ),
                ),
            ],
        ];
    }

    #[DataProvider('dispatchMachineIsActiveEventIsActiveDataProvider')]
    public function testDispatchMachineIsActiveEventMachineIsActive(
        MachineEntity $currentMachine,
        MachineRetrievedEvent $event,
        MachineIsActiveEvent $expected,
    ): void {
        $this->machineRepository->save($currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([$expected], $this->eventRecorder->all(MachineIsActiveEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function dispatchMachineIsActiveEventIsActiveDataProvider(): array
    {
        $machineId = Id::generate();

        $unknownMachine = new Machine(
            $machineId,
            'unknown',
            'unknown',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $unknownMachineEntity = self::createMachineEntityFromMachine($unknownMachine);

        $findingMachine = new Machine(
            $machineId,
            'find/finding',
            'pre_active',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $findingMachineEntity = self::createMachineEntityFromMachine($findingMachine);

        $activeMachine = new Machine(
            $machineId,
            'up/acive',
            'active',
            '127.0.0.1',
            null,
            new MetaState(false, false, false),
            true,
            false,
            false,
        );

        return [
            'unknown -> active' => [
                'currentMachine' => $unknownMachineEntity,
                'event' => new MachineRetrievedEvent($activeMachine),
                'expected' => new MachineIsActiveEvent('127.0.0.1', $activeMachine),
            ],
            'finding -> active' => [
                'currentMachine' => $findingMachineEntity,
                'event' => new MachineRetrievedEvent($activeMachine),
                'expected' => new MachineIsActiveEvent('127.0.0.1', $activeMachine),
            ],
        ];
    }

    public function testDispatchMachineHasActionFailureEventNoActionFailure(): void
    {
        $machineId = Id::generate();

        $previousMachine = new Machine(
            $machineId,
            'previous-state',
            'active',
            null,
            null,
            new MetaState(false, false, false),
            false,
            false,
            false,
        );

        $this->persistMachineModel($previousMachine);

        $currentMachine = new Machine(
            $machineId,
            'current-state',
            'active',
            null,
            null,
            new MetaState(false, false, false),
            false,
            false,
            false,
        );

        $event = new MachineRetrievedEvent($currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineHasActionFailureEvent::class));
    }

    public function testDispatchMachineHasActionFailureEvenHasActionFailure(): void
    {
        $machineId = Id::generate();
        $actionFailure = new ActionFailure('find', 'vendor_authentication_failure', []);

        $previousMachine = new Machine(
            $machineId,
            'find/finding',
            'finding',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $this->persistMachineModel($previousMachine);

        $currentMachine = new Machine(
            $machineId,
            'find/not-findable',
            'finding',
            null,
            $actionFailure,
            new MetaState(true, false, false),
            false,
            false,
            false,
        );

        $event = new MachineRetrievedEvent($currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals(
            [
                new MachineHasActionFailureEvent($currentMachine),
            ],
            $this->eventRecorder->all(MachineHasActionFailureEvent::class)
        );
    }

    private function persistMachineModel(MachineInterface $model): void
    {
        $entity = self::createMachineEntityFromMachine($model);
        $this->machineRepository->save($entity);
    }

    private static function createMachineEntityFromMachine(MachineInterface $model): MachineEntity
    {
        $entity = new MachineEntity(
            $model->getId(),
            $model->getState(),
            $model->getStateCategory(),
        )
            ->setMetaState($model->getMetaState())
        ;

        if ($model->isActive()) {
            $entity->setIsActive();
        }

        return $entity;
    }
}
