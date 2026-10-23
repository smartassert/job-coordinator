<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventDispatcher;

use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\Machine\WorkerManagerMachine;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use App\Tests\Services\Generator\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use SmartAssert\WorkerManagerClient\Model\MetaState as WorkerManagerClientMetaState;
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

        $event = new MachineRetrievedEvent(
            new WorkerManagerMachine(
                MachineFactory::create(
                    $machineId,
                    'unchanged-state',
                    'active',
                    [],
                    true,
                    false,
                    new WorkerManagerClientMetaState(false, false, false),
                )
            ),
            new WorkerManagerMachine(
                MachineFactory::create(
                    $machineId,
                    'unchanged-state',
                    'active',
                    [],
                    true,
                    false,
                    new WorkerManagerClientMetaState(false, false, false),
                ),
            ),
        );

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineStateChangeEvent::class));
    }

    public function testDispatchMachineStateChangeEventHasStateChange(): void
    {
        $machineId = Id::generate();

        $previousMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'previous-state',
                'active',
                [],
                true,
                false,
                new WorkerManagerClientMetaState(false, false, false),
            ),
        );

        $currentMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'current-state',
                'active',
                [],
                true,
                false,
                new WorkerManagerClientMetaState(false, false, false),
            ),
        );

        $event = new MachineRetrievedEvent($previousMachine, $currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals(
            [
                new MachineStateChangeEvent($previousMachine, $currentMachine),
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

        return [
            'unknown -> unknown' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                ),
            ],
            'unknown -> finding' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'find/finding',
                            'pre_active',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                ),
            ],
            'finding -> finding' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'find/finding',
                            'pre_active',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'find/finding',
                            'pre_active',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                ),
            ],
            'finding -> active without ip address' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'find/finding',
                            'pre_active',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'up/active',
                            'active',
                            [],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
                        ),
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

        return [
            'unknown -> active' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'up/active',
                            'active',
                            ['127.0.0.1'],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
                        ),
                    ),
                ),
                'expected' => new MachineIsActiveEvent(
                    '127.0.0.1',
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'up/active',
                            'active',
                            ['127.0.0.1'],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
                        ),
                    )
                ),
            ],
            'finding -> active' => [
                'event' => new MachineRetrievedEvent(
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'find/finding',
                            'pre_active',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                    ),
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'up/active',
                            'active',
                            ['127.0.0.2'],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
                        ),
                    ),
                ),
                'expected' => new MachineIsActiveEvent(
                    '127.0.0.2',
                    new WorkerManagerMachine(
                        MachineFactory::create(
                            $machineId,
                            'up/active',
                            'active',
                            ['127.0.0.2'],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
                        ),
                    )
                ),
            ],
        ];
    }

    public function testDispatchMachineHasActionFailureEventNoActionFailure(): void
    {
        $machineId = Id::generate();

        $previousMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'previous-state',
                'active',
                [],
                true,
                false,
                new WorkerManagerClientMetaState(false, false, false),
            ),
        );

        $currentMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'current-state',
                'active',
                [],
                true,
                false,
                new WorkerManagerClientMetaState(false, false, false),
            ),
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

        $previousMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'find/finding',
                'finding',
                [],
                false,
                false,
                new WorkerManagerClientMetaState(false, false, true),
            ),
        );

        $currentMachine = new WorkerManagerMachine(
            MachineFactory::create(
                $machineId,
                'find/not-findable',
                'end',
                [],
                false,
                true,
                new WorkerManagerClientMetaState(true, false, false),
                $actionFailure
            ),
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
}
