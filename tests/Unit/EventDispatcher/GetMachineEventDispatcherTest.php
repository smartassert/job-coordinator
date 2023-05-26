<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventDispatcher;

use App\Event\MachineRetrievedEvent;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Repository\JobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetMachineEventDispatcherTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchIfMachineNotInEndStateForMachineInEndState(): void
    {
        $machineId = md5((string) rand());
        $machine = new Machine($machineId, 'an_end_state', 'end', []);

        $messageDispatcher = \Mockery::mock(JobRemoteRequestMessageDispatcher::class);
        $messageDispatcher
            ->shouldNotReceive('dispatch')
        ;
        $messageDispatcher
            ->shouldNotReceive('dispatchWithNonDelayedStamp')
        ;

        $dispatcher = new GetMachineMessageDispatcher(\Mockery::mock(JobRepository::class), $messageDispatcher);
        $event = new MachineRetrievedEvent(md5((string) rand()), $machine, $machine);

        $dispatcher->dispatchIfMachineNotInEndState($event);
    }
}
