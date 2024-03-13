<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerJobStartRequestedEvent;
use App\Services\RemoteRequestRemover;
use App\Services\RemoteRequestRemoverForEvents;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use App\Tests\Services\Factory\SourcesClientSerializedSuiteFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;

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

        $resultsClientJob = ResultsClientJobFactory::createRandom();

        $remoteRequestRemoverForEvents->removeResultsCreateRequests(
            new ResultsJobCreatedEvent('authentication token', $jobId, $resultsClientJob)
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
            new SerializedSuiteCreatedEvent(
                'authentication token',
                $jobId,
                SourcesClientSerializedSuiteFactory::create(md5((string) rand()))
            )
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

        $remoteRequestRemoverForEvents->removeMachineGetRequests(
            new MachineRetrievedEvent(
                'authentication token',
                WorkerManagerClientMachineFactory::createRandom(),
                WorkerManagerClientMachineFactory::create($jobId, 'state', 'state-category', []),
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
            new SerializedSuiteRetrievedEvent(
                'authentication token',
                $jobId,
                SourcesClientSerializedSuiteFactory::create(md5((string) rand()))
            )
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
            new WorkerJobStartRequestedEvent($jobId, '127.0.0.1', WorkerClientJobFactory::createRandom())
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
            new ResultsJobStateRetrievedEvent(
                'authentication token',
                $jobId,
                new ResultsJobState('irrelevant', 'irrelevant')
            )
        );

        self::assertTrue(true);
    }

    public function testRemoveMachineTerminationRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->with($jobId, RemoteRequestType::MACHINE_TERMINATE)
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineTerminationRequests(
            new MachineTerminationRequestedEvent('authentication token', $jobId)
        );

        self::assertTrue(true);
    }
}
