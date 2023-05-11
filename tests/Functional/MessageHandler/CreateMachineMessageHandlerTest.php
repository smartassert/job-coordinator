<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Exception\MachineCreationException;
use App\Message\CheckMachineStateChangeMessage;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
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

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);
        self::assertSame(RequestState::UNKNOWN, $job->getResultsJobRequestState());

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
            self::assertSame($workerManagerException, $e->previousException);
            $this->assertNoMessagesDispatched();
        }

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $retrievedJob = $jobRepository->find($job->id);
        \assert($retrievedJob instanceof Job);

        self::assertSame(RequestState::HALTED, $retrievedJob->getMachineRequestState());
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);
        self::assertSame(RequestState::UNKNOWN, $job->getMachineRequestState());

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

        self::assertSame(RequestState::SUCCEEDED, $job->getMachineRequestState());
        self::assertSame($machine->stateCategory, $job->getMachineStateCategory());

        $this->assertDispatchedMessage(self::$apiToken, $machine);
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
     * @param non-empty-string $authenticationToken
     */
    private function assertDispatchedMessage(string $authenticationToken, Machine $machine): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new CheckMachineStateChangeMessage($authenticationToken, $machine),
            $envelope->getMessage()
        );

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
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
            $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
            \assert($workerManagerClient instanceof WorkerManagerClient);
        }

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
    }
}
