<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\MachineTerminationRequestedEvent;
use App\Exception\MachineTerminationException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\ClientInterface as WorkerManagerClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class TerminateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('find')
            ->with($jobId)
            ->andReturnNull()
        ;

        $handler = $this->createHandler(
            jobRepository: $jobRepository,
        );

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $job = $this->createJob();

        $workerManagerException = new \Exception('Failed to terminate machine');

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('deleteMachine')
            ->with(self::$apiToken, $job->id)
            ->andThrow($workerManagerException)
        ;

        $handler = $this->createHandler(
            workerManagerClient: $workerManagerClient,
        );

        $message = new TerminateMachineMessage(self::$apiToken, $job->id);

        try {
            $handler($message);
            self::fail(MachineTerminationException::class . ' not thrown');
        } catch (MachineTerminationException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();

        $machine = MachineFactory::create($job->id, 'create/requested', 'pre_active', []);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('deleteMachine')
            ->with(self::$apiToken, $job->id)
            ->andReturn($machine)
        ;

        $handler = $this->createHandler(
            workerManagerClient: $workerManagerClient,
        );

        $handler(new TerminateMachineMessage(self::$apiToken, $job->id));

        $events = $this->eventRecorder->all(MachineTerminationRequestedEvent::class);
        $event = $events[0] ?? null;
        self::assertInstanceOf(MachineTerminationRequestedEvent::class, $event);

        self::assertSame($job->id, $event->jobId);
        self::assertSame(self::$apiToken, $event->authenticationToken);
    }

    protected function getHandlerClass(): string
    {
        return TerminateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return TerminateMachineMessage::class;
    }

    private function createJob(): Job
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?WorkerManagerClient $workerManagerClient = null,
    ): TerminateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        if (null === $jobRepository) {
            $jobRepository = self::getContainer()->get(JobRepository::class);
            \assert($jobRepository instanceof JobRepository);
        }

        if (null === $workerManagerClient) {
            $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        }

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new TerminateMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
    }
}
