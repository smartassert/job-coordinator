<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Model\RemoteRequestType;
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
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE),
                    $passedRemoteRequestType,
                );

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineCreateRequests(
            new MachineIsActiveEvent('authentication token', $jobId, '127.0.0.1')
        );
    }

    public function testRemoveResultsCreateRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::CREATE),
                    $passedRemoteRequestType,
                );

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $resultsClientJob = ResultsClientJobFactory::createRandom();

        $remoteRequestRemoverForEvents->removeResultsCreateRequests(
            new ResultsJobCreatedEvent('authentication token', $jobId, $resultsClientJob)
        );
    }

    public function testRemoveSerializedSuiteCreateRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::SERIALIZED_SUITE, RemoteRequestAction::CREATE),
                    $passedRemoteRequestType,
                );

                return true;
            })
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
    }

    public function testRemoveMachineGetRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::RETRIEVE),
                    $passedRemoteRequestType,
                );

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineGetRequests(
            new MachineRetrievedEvent(
                'authentication token',
                WorkerManagerClientMachineFactory::createRandomForJob($jobId),
                WorkerManagerClientMachineFactory::createRandomForJob($jobId),
            )
        );
    }

    public function testRemoveSerializedSuiteGetRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::SERIALIZED_SUITE, RemoteRequestAction::RETRIEVE),
                    $passedRemoteRequestType,
                );

                return true;
            })
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
    }

    public function testRemoveWorkerJobStartRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::WORKER_JOB, RemoteRequestAction::CREATE),
                    $passedRemoteRequestType,
                );

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeWorkerJobCreateRequests(
            new CreateWorkerJobRequestedEvent($jobId, '127.0.0.1', WorkerClientJobFactory::createRandom())
        );
    }

    public function testRemoveResultsStateGetRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::RETRIEVE),
                    $passedRemoteRequestType,
                );

                return true;
            })
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
    }

    public function testRemoveMachineTerminationRequests(): void
    {
        $jobId = md5((string) rand());

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(
                    new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::TERMINATE),
                    $passedRemoteRequestType,
                );

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineTerminationRequests(
            new MachineTerminationRequestedEvent($jobId)
        );
    }
}
