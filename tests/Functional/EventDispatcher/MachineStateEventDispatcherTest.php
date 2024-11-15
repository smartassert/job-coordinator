<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventDispatcher;

use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Tests\Services\EventSubscriber\EventRecorder;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Ulid;

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
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $event = new MachineRetrievedEvent(
            md5((string) rand()),
            MachineFactory::create(
                $machineId,
                'unchanged-state',
                'active',
                [],
                false,
                true,
                false,
                false,
            ),
            MachineFactory::create(
                $machineId,
                'unchanged-state',
                'active',
                [],
                false,
                true,
                false,
                false,
            ),
        );

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineStateChangeEvent::class));
    }

    public function testDispatchMachineStateChangeEventHasStateChange(): void
    {
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $authenticationToken = md5((string) rand());

        $previousMachine = MachineFactory::create(
            $machineId,
            'previous-state',
            'active',
            [],
            false,
            true,
            false,
            false,
        );

        $currentMachine = MachineFactory::create(
            $machineId,
            'current-state',
            'active',
            [],
            false,
            true,
            false,
            false,
        );

        $event = new MachineRetrievedEvent($authenticationToken, $previousMachine, $currentMachine);

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
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        return [
            'unknown -> unknown' => [
                'event' => new MachineRetrievedEvent(
                    md5((string) rand()),
                    MachineFactory::create(
                        $machineId,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                ),
            ],
            'unknown -> finding' => [
                'event' => new MachineRetrievedEvent(
                    md5((string) rand()),
                    MachineFactory::create(
                        $machineId,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'find/finding',
                        'pre_active',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                ),
            ],
            'finding -> finding' => [
                'event' => new MachineRetrievedEvent(
                    md5((string) rand()),
                    MachineFactory::create(
                        $machineId,
                        'find/finding',
                        'pre_active',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'find/finding',
                        'pre_active',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                ),
            ],
            'finding -> active without ip address' => [
                'event' => new MachineRetrievedEvent(
                    md5((string) rand()),
                    MachineFactory::create(
                        $machineId,
                        'find/finding',
                        'pre_active',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'up/active',
                        'active',
                        [],
                        false,
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
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $authenticationToken = md5((string) rand());

        return [
            'unknown -> active' => [
                'event' => new MachineRetrievedEvent(
                    $authenticationToken,
                    MachineFactory::create(
                        $machineId,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'up/active',
                        'active',
                        ['127.0.0.1'],
                        false,
                        true,
                        false,
                        false,
                    ),
                ),
                'expected' => new MachineIsActiveEvent(
                    $authenticationToken,
                    $machineId,
                    '127.0.0.1',
                    MachineFactory::create(
                        $machineId,
                        'up/active',
                        'active',
                        ['127.0.0.1'],
                        false,
                        true,
                        false,
                        false,
                    )
                ),
            ],
            'finding -> active' => [
                'event' => new MachineRetrievedEvent(
                    $authenticationToken,
                    MachineFactory::create(
                        $machineId,
                        'find/finding',
                        'pre_active',
                        [],
                        false,
                        false,
                        false,
                        false,
                    ),
                    MachineFactory::create(
                        $machineId,
                        'up/active',
                        'active',
                        ['127.0.0.2'],
                        false,
                        true,
                        false,
                        false,
                    ),
                ),
                'expected' => new MachineIsActiveEvent(
                    $authenticationToken,
                    $machineId,
                    '127.0.0.2',
                    MachineFactory::create(
                        $machineId,
                        'up/active',
                        'active',
                        ['127.0.0.2'],
                        false,
                        true,
                        false,
                        false,
                    )
                ),
            ],
        ];
    }

    public function testDispatchMachineHasActionFailureEventNoActionFailure(): void
    {
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $authenticationToken = md5((string) rand());

        $previousMachine = MachineFactory::create(
            $machineId,
            'previous-state',
            'active',
            [],
            false,
            true,
            false,
            false,
        );

        $currentMachine = MachineFactory::create(
            $machineId,
            'current-state',
            'active',
            [],
            false,
            true,
            false,
            false,
        );

        $event = new MachineRetrievedEvent($authenticationToken, $previousMachine, $currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals([], $this->eventRecorder->all(MachineHasActionFailureEvent::class));
    }

    public function testDispatchMachineHasActionFailureEvenHasActionFailure(): void
    {
        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $authenticationToken = md5((string) rand());
        $actionFailure = new ActionFailure('find', 'vendor_authentication_failure', []);

        $previousMachine = MachineFactory::create(
            $machineId,
            'find/finding',
            'finding',
            [],
            false,
            false,
            false,
            false,
        );
        $currentMachine = MachineFactory::create(
            $machineId,
            'find/not-findable',
            'end',
            [],
            true,
            false,
            false,
            true,
            $actionFailure
        );

        $event = new MachineRetrievedEvent($authenticationToken, $previousMachine, $currentMachine);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $eventDispatcher->dispatch($event);

        self::assertEquals(
            [
                new MachineHasActionFailureEvent($machineId, $actionFailure, $currentMachine),
            ],
            $this->eventRecorder->all(MachineHasActionFailureEvent::class)
        );
    }
}
