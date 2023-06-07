<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Services\RemoteRequestRemover;
use App\Services\RemoteRequestRemoverForEvents;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\WorkerManagerClient\Model\Machine;

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

    public function testRemoveResultsCreateRemoteRequestsForResultsJobCreatedEvent(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::RESULTS_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeResultsCreateRemoteRequestsForResultsJobCreatedEvent(
            new ResultsJobCreatedEvent('authentication token', $jobId, \Mockery::mock(ResultsJob::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveSerializedSuiteCreateRequestsForSerializedSuiteCreatedEvent(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeSerializedSuiteCreateRequestsForSerializedSuiteCreatedEvent(
            new SerializedSuiteCreatedEvent('authentication token', $jobId, \Mockery::mock(SerializedSuite::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveMachineGetRemoteRequestsForMachineRetrievedEvent(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::MACHINE_GET)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $currentMachine = new Machine($jobId, 'state', 'state-category', []);

        $remoteRequestRemoverForEvents->removeMachineGetRemoteRequestsForMachineRetrievedEvent(
            new MachineRetrievedEvent(
                'authentication token',
                \Mockery::mock(Machine::class),
                $currentMachine,
            )
        );

        self::assertTrue(true);
    }

    public function testRemoveSerializedSuiteGetRemoteRequestsForSerializedSuiteRetrievedEvent(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::SERIALIZED_SUITE_GET)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $currentMachine = new Machine($jobId, 'state', 'state-category', []);

        $remoteRequestRemoverForEvents->removeSerializedSuiteGetRemoteRequestsForSerializedSuiteRetrievedEvent(
            new SerializedSuiteRetrievedEvent('authentication token', $jobId, \Mockery::mock(SerializedSuite::class))
        );

        self::assertTrue(true);
    }
}
