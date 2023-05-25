<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use PHPUnit\Framework\TestCase;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GetMachineMessageHandlerTest extends TestCase
{
    public function testInvokeMachineRetrievalThrowsException(): void
    {
        $machineId = md5((string) rand());
        $machine = new Machine($machineId, 'up/active', 'active', ['127.0.0.1']);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->andThrow(new \Exception())
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldNotReceive('dispatch')
        ;

        $handler = new GetMachineMessageHandler($workerManagerClient, $eventDispatcher);

        $authenticationToken = md5((string) rand());

        $message = new GetMachineMessage($authenticationToken, $machine->id, $machine);

        self::expectException(MachineRetrievalException::class);

        ($handler)($message);
    }
}
