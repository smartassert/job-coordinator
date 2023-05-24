<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Event\RemoteRequestEventInterface;
use App\Event\RemoteRequestFailedEvent;
use App\Event\RemoteRequestStartedEvent;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Services\RemoteRequestFactory;
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

        $remoteRequest = \Mockery::mock(RemoteRequest::class);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->andThrow(new \Exception())
        ;

        $eventDispatcherDispatchCount = 0;
        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function (
                RemoteRequestEventInterface $event
            ) use (
                $remoteRequest,
                &$eventDispatcherDispatchCount
            ) {
                $expectedRemoteRequestEventClasses = [
                    RemoteRequestStartedEvent::class,
                    RemoteRequestFailedEvent::class,
                ];

                self::assertInstanceOf($expectedRemoteRequestEventClasses[$eventDispatcherDispatchCount], $event);
                self::assertSame($remoteRequest, $event->getRemoteRequest());

                ++$eventDispatcherDispatchCount;

                return true;
            })
        ;

        $remoteRequestFactory = \Mockery::mock(RemoteRequestFactory::class);
        $remoteRequestFactory
            ->shouldReceive('create')
            ->with($machineId, RemoteRequestType::MACHINE_GET)
            ->andReturn($remoteRequest)
        ;

        $handler = new GetMachineMessageHandler(
            $workerManagerClient,
            $eventDispatcher,
            $remoteRequestFactory,
        );

        $authenticationToken = md5((string) rand());

        $message = new GetMachineMessage($authenticationToken, $machine);

        self::expectException(MachineRetrievalException::class);

        ($handler)($message);
    }
}
