<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Services\RemoteRequestRemover;
use App\Services\RemoteRequestRemoverForEvents;
use PHPUnit\Framework\TestCase;

class RemoteRequestRemoverForEventsTest extends TestCase
{
    public function testRemoveMachineCreateRemoteRequestsForMachineIsActiveEvent(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::MACHINE_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineCreateRemoteRequestsForMachineIsActiveEvent(
            new MachineIsActiveEvent('authentication token', $jobId, '127.0.0.1')
        );

        self::assertTrue(true);
    }
}
