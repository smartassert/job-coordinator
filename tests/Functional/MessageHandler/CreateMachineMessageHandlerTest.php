<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private EventRecorder $eventRecorder;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
    }

    public function testInvokeNoJob(): void
    {
        $jobId = md5((string) rand());

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('find')
            ->with($jobId)
            ->andReturnNull()
        ;

        $handler = $this->createHandler(
            jobRepository: $jobRepository,
        );

        $message = new CreateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('createMachine')
            ->with(self::$apiToken, $job->id)
            ->andThrow($workerManagerException)
        ;

        $handler = $this->createHandler(
            workerManagerClient: $workerManagerClient,
        );

        $message = new CreateMachineMessage(self::$apiToken, $jobId);

        try {
            $handler($message);
            self::fail(MachineCreationException::class . ' not thrown');
        } catch (MachineCreationException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

        $machine = new Machine($jobId, 'create/requested', 'pre_active', []);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('createMachine')
            ->with(self::$apiToken, $job->id)
            ->andReturn($machine)
        ;

        $handler = $this->createHandler(
            workerManagerClient: $workerManagerClient,
        );

        self::assertNull($job->getResultsToken());

        $handler(new CreateMachineMessage(self::$apiToken, $jobId));

        self::assertSame($machine->stateCategory, $job->getMachineStateCategory());

        $events = $this->eventRecorder->all(MachineCreationRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new MachineCreationRequestedEvent(self::$apiToken, $machine),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return CreateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateMachineMessage::class;
    }

    /**
     * @param non-empty-string $jobId
     */
    private function createJob(string $jobId): Job
    {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            600
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?WorkerManagerClient $workerManagerClient = null,
    ): CreateMachineMessageHandler {
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

        return new CreateMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
    }
}
