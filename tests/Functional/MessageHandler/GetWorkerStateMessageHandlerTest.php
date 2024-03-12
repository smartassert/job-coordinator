<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\WorkerStateRetrievedEvent;
use App\Exception\WorkerStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\Message\GetWorkerStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\MessageHandler\GetWorkerStateMessageHandler;
use App\Repository\JobRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerClient\ClientInterface as WorkerClient;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Component\Uid\Ulid;

class GetWorkerStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $handler = $this->createHandler(\Mockery::mock(WorkerClientFactory::class));

        $message = new GetWorkerStateMessage($jobId, '127.0.0.1');

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    public function testInvokeWorkerClientThrowsException(): void
    {
        $job = $this->createJob();

        $workerClientException = new \Exception('Failed to get worker state');

        $workerClient = \Mockery::mock(WorkerClient::class);
        $workerClient
            ->shouldReceive('getApplicationState')
            ->withNoArgs()
            ->andThrow($workerClientException)
        ;

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerStateMessage($job->id, $machineIpAddress);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory);

        try {
            $handler($message);
            self::fail(WorkerStateRetrievalException::class . ' not thrown');
        } catch (WorkerStateRetrievalException $e) {
            self::assertSame($workerClientException, $e->getPreviousException());
            self::assertCount(0, $this->eventRecorder);
        }
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerStateMessage($job->id, $machineIpAddress);

        $retrievedWorkerState = new ApplicationState(
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1))
        );

        $workerClient = \Mockery::mock(WorkerClient::class);
        $workerClient
            ->shouldReceive('getApplicationState')
            ->withNoArgs()
            ->andReturn($retrievedWorkerState)
        ;

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new WorkerStateRetrievedEvent($job->id, $machineIpAddress, $retrievedWorkerState), $event);
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobStateMessage::class;
    }

    private function createJob(): Job
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(WorkerClientFactory $workerClientFactory): GetWorkerStateMessageHandler
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetWorkerStateMessageHandler($jobRepository, $workerClientFactory, $eventDispatcher);
    }
}
