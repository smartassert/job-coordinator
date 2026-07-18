<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Model\RemoteRequestType;
use App\Services\RemoteRequestRemover;
use App\Services\RemoteRequestRemoverForEvents;
use App\Tests\Services\Factory\ResultsClientJobFactory;
use App\Tests\Services\Factory\SourcesClientSerializedSuiteFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\Ip;
use App\Tests\Services\Generator\StringValue;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
use SmartAssert\WorkerManagerClient\Model\MetaState as WorkerManagerClientMetaState;

class RemoteRequestRemoverForEventsTest extends TestCase
{
    public function testRemoveMachineCreateRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForMachineCreation(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineCreateRequests(
            new MachineIsActiveEvent(
                $jobId,
                '127.0.0.1',
                MachineFactory::create(
                    $jobId,
                    StringValue::random(),
                    StringValue::random(),
                    [Ip::random()],
                    false,
                    false,
                    new WorkerManagerClientMetaState(false, false, true)
                )
            )
        );
    }

    public function testRemoveResultsCreateRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForResultsJobCreation(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $resultsClientJob = ResultsClientJobFactory::createRandom();

        $remoteRequestRemoverForEvents->removeResultsCreateRequests(
            new ResultsJobCreatedEvent($jobId, $resultsClientJob)
        );
    }

    public function testRemoveSerializedSuiteCreateRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForSerializedSuiteCreation(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $serializedSuiteId = Id::generate();
        $suiteId = Id::generate();

        $remoteRequestRemoverForEvents->removeSerializedSuiteCreateRequests(
            new SerializedSuiteCreatedEvent(
                $jobId,
                SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $suiteId)
            )
        );
    }

    public function testRemoveMachineGetRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForMachineRetrieval(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineGetRequests(
            new MachineRetrievedEvent(
                WorkerManagerClientMachineFactory::createRandomForJob($jobId),
                WorkerManagerClientMachineFactory::createRandomForJob($jobId),
            )
        );
    }

    public function testRemoveSerializedSuiteGetRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForSerializedSuiteRetrieval(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $serializedSuiteId = Id::generate();
        $suiteId = Id::generate();

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);
        $remoteRequestRemoverForEvents->removeSerializedSuiteGetRequests(
            new SerializedSuiteRetrievedEvent(
                $jobId,
                SourcesClientSerializedSuiteFactory::create($serializedSuiteId, $suiteId)
            )
        );
    }

    public function testRemoveWorkerJobStartRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForWorkerJobCreation(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeWorkerJobCreateRequests(
            new CreateWorkerJobRequestedEvent(
                $jobId,
                '127.0.0.1',
                WorkerClientJobFactory::createRandom(),
            )
        );
    }

    public function testRemoveResultsJobGetRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForResultsJobRetrieval(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeResultsJobGetRequests(
            new ResultsJobRetrievedEvent(
                $jobId,
                new ResultsJob(
                    'job-label',
                    '/event/add/results-token',
                    new ResultsJobState(
                        'irrelevant',
                        'irrelevant',
                        new ResultsClientMetaState(false, false, false),
                    ),
                    false,
                    [],
                ),
            )
        );
    }

    public function testRemoveMachineTerminationRequests(): void
    {
        $jobId = Id::generate();

        $remoteRequestRemover = \Mockery::mock(RemoteRequestRemover::class);
        $remoteRequestRemover
            ->shouldReceive('removeForJobAndType')
            ->withArgs(function ($passedJobId, $passedRemoteRequestType) use ($jobId) {
                self::assertSame($jobId, $passedJobId);
                self::assertEquals(RemoteRequestType::createForMachineTermination(), $passedRemoteRequestType);

                return true;
            })
            ->andReturn([])
        ;

        $remoteRequestRemoverForEvents = new RemoteRequestRemoverForEvents($remoteRequestRemover);

        $remoteRequestRemoverForEvents->removeMachineTerminationRequests(
            new MachineTerminationRequestedEvent(
                $jobId,
                MachineFactory::create(
                    $jobId,
                    StringValue::random(),
                    StringValue::random(),
                    [Ip::random()],
                    false,
                    false,
                    new WorkerManagerClientMetaState(false, false, true),
                )
            )
        );
    }
}
