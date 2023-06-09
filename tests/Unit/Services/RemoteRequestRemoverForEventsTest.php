<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerJobStartRequestedEvent;
use App\Services\RemoteRequestRemover;
use App\Services\RemoteRequestRemoverForEvents;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\WorkerClient\Model\Job as WorkerJob;
use SmartAssert\WorkerManagerClient\Model\Machine;

class RemoteRequestRemoverForEventsTest extends TestCase
{
    public function testRemoveMachineCreateRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::MACHINE_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineCreateRequests(
            new MachineIsActiveEvent('authentication token', $jobId, '127.0.0.1')
        );

        self::assertTrue(true);
    }

    public function testRemoveResultsCreateRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::RESULTS_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeResultsCreateRequests(
            new ResultsJobCreatedEvent('authentication token', $jobId, \Mockery::mock(ResultsJob::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveSerializedSuiteCreateRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeSerializedSuiteCreateRequests(
            new SerializedSuiteCreatedEvent('authentication token', $jobId, \Mockery::mock(SerializedSuite::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveMachineGetRequests(): void
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

        $remoteRequestRemoverForEvents->removeMachineGetRequests(
            new MachineRetrievedEvent(
                'authentication token',
                \Mockery::mock(Machine::class),
                $currentMachine,
            )
        );

        self::assertTrue(true);
    }

    public function testRemoveSerializedSuiteGetRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::SERIALIZED_SUITE_GET)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);
        $remoteRequestRemoverForEvents->removeSerializedSuiteGetRequests(
            new SerializedSuiteRetrievedEvent('authentication token', $jobId, \Mockery::mock(SerializedSuite::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveWorkerJobStartRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::MACHINE_START_JOB)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeWorkerJobStartRequests(
            new WorkerJobStartRequestedEvent('authentication token', $jobId, \Mockery::mock(WorkerJob::class))
        );

        self::assertTrue(true);
    }

    public function testRemoveResultsStateGetRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::RESULTS_STATE_GET)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeResultsStateGetRequests(
            new ResultsJobStateRetrievedEvent('authentication token', $jobId, \Mockery::mock(ResultsJobState::class))
        );

        self::assertTrue(true);
    }
}
