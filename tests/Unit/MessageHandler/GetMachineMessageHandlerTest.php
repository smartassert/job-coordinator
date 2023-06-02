<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Job;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Repository\JobRepository;
use PHPUnit\Framework\TestCase;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GetMachineMessageHandlerTest extends TestCase
{
    public function testInvokeMachineRetrievalThrowsException(): void
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $machine = new Machine($job->id, 'up/active', 'active', ['127.0.0.1']);

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('find')
            ->with($job->id)
            ->andReturn($job)
        ;

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->andThrow(new \Exception())
        ;

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldNotReceive('dispatch')
        ;

        $handler = new GetMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);

        $authenticationToken = md5((string) rand());

        $message = new GetMachineMessage($authenticationToken, $machine->id, $machine);

        self::expectException(MachineRetrievalException::class);

        ($handler)($message);
    }
}
