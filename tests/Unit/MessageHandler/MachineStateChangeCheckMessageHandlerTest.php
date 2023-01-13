<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\MachineStateChangeCheckMessage;
use App\MessageDispatcher\MachineStateChangeCheckMessageDispatcher;
use App\MessageHandler\MachineStateChangeCheckMessageHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MachineStateChangeCheckMessageHandlerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    public function testHandleChangeToEndState(): void
    {
        $machineId = md5((string) rand());

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->andReturn(new Machine($machineId, 'an_end_state', [], true))
        ;

        $messageDispatcher = \Mockery::mock(MachineStateChangeCheckMessageDispatcher::class);
        $messageDispatcher
            ->shouldNotHaveReceived('dispatch')
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
        ;

        $handler = new MachineStateChangeCheckMessageHandler(
            $messageDispatcher,
            $workerManagerClient,
            $eventDispatcher,
        );

        $authenticationToken = md5((string) rand());
        $message = new MachineStateChangeCheckMessage($authenticationToken, $machineId, null);

        ($handler)($message);
    }
}
