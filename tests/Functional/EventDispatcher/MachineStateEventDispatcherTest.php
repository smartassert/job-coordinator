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
use App\Model\MetaState;
use App\Repository\MachineRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Generator\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MachineStateEventDispatcherTest extends WebTestCase
{
    private EventRecorder $eventRecorder;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
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

        $event = new MachineRetrievedEvent($machineModel, $machineModel);

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

        $event = new MachineRetrievedEvent($previousMachineModel, $currentMachineModel);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals(
            [
                new MachineStateChangeEvent($previousMachineModel, $currentMachineModel),
            ],
            $this->eventRecorder->all(MachineStateChangeEvent::class)
        );
    }

    #[DataProvider('dispatchMachineIsActiveEventNotActiveDataProvider')]
    public function testDispatchMachineIsActiveEventMachineNotActive(MachineRetrievedEvent $event): void
    {
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

        return [
            'unknown -> unknown' => [
                'event' => new MachineRetrievedEvent($unknownMachine, $unknownMachine),
            ],
            'unknown -> finding' => [
                'event' => new MachineRetrievedEvent($unknownMachine, $findingMachine),
            ],
            'finding -> finding' => [
                'event' => new MachineRetrievedEvent($findingMachine, $findingMachine),
            ],
            'finding -> active without ip address' => [
                'event' => new MachineRetrievedEvent(
                    $findingMachine,
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
        MachineRetrievedEvent $event,
        MachineIsActiveEvent $expected,
    ): void {
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
                'event' => new MachineRetrievedEvent($unknownMachine, $activeMachine),
                'expected' => new MachineIsActiveEvent('127.0.0.1', $activeMachine),
            ],
            'finding -> active' => [
                'event' => new MachineRetrievedEvent($findingMachine, $activeMachine),
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

        $event = new MachineRetrievedEvent($previousMachine, $currentMachine);

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

        $event = new MachineRetrievedEvent($previousMachine, $currentMachine);

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

    private function persistMachineModel(Machine $model): void
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

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save($entity);
    }
}
