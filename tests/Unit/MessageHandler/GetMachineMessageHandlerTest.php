<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class GetMachineMessageHandlerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    public function testHandleChangeToEndState(): void
    {
        $machineId = md5((string) rand());
        $machine = new Machine($machineId, 'an_end_state', 'end', []);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->andReturn($machine)
        ;

        $messageBus = \Mockery::mock(MessageBusInterface::class);
        $messageBus
            ->shouldNotHaveReceived('dispatch')
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
        ;

        $handler = new GetMachineMessageHandler(
            $messageBus,
            $workerManagerClient,
            $eventDispatcher,
        );

        $authenticationToken = md5((string) rand());
        $message = new GetMachineMessage($authenticationToken, $machine);

        ($handler)($message);
    }
}
