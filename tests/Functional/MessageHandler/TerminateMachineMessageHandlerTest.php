<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Exception\MachineTerminationException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\Repository\JobRepository;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\Messenger\MessageBusInterface;

class TerminateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
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

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

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

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        try {
            $handler($message);
            self::fail(MachineTerminationException::class . ' not thrown');
        } catch (MachineTerminationException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            $this->assertNoMessagesDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

        $machine = new Machine($jobId, 'create/requested', 'pre_active', []);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('deleteMachine')
            ->with(self::$apiToken, $job->id)
            ->andReturn($machine)
        ;

        $handler = $this->createHandler(
            workerManagerClient: $workerManagerClient,
        );

        $handler(new TerminateMachineMessage(self::$apiToken, $jobId));

        $this->assertNoMessagesDispatched();
    }

    protected function getHandlerClass(): string
    {
        return TerminateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return TerminateMachineMessage::class;
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

        return new TerminateMachineMessageHandler($jobRepository, $workerManagerClient);
    }
}
