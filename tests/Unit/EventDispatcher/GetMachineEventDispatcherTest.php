<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventDispatcher;

use App\Event\MachineRetrievedEvent;
use App\MessageDispatcher\GetMachineMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\Repository\JobRepository;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GetMachineEventDispatcherTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    public function testDispatchIfMachineNotInEndStateForMachineInEndState(): void
    {
        $machineId = md5((string) rand());
        $machine = MachineFactory::create($machineId, 'an_end_state', 'end', [], false);

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
