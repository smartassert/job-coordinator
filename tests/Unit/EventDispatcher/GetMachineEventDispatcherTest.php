<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventDispatcher;

use App\Event\MachineRetrievedEvent;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\Repository\JobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class GetMachineEventDispatcherTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchIfMachineNotInEndStateForMachineInEndState(): void
    {
        $machineId = md5((string) rand());
        $machine = new Machine($machineId, 'an_end_state', 'end', []);

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldNotHaveReceived('dispatch')
        ;

        $dispatcher = new GetMachineMessageDispatcher(
            \Mockery::mock(JobRepository::class),
            $messageBus,
            \Mockery::mock(EventDispatcherInterface::class)
        );
        $event = new MachineRetrievedEvent(md5((string) rand()), $machine, $machine);

        $dispatcher->dispatchIfMachineNotInEndState($event);
    }
}
