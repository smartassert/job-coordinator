<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineTerminationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
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
            $jobRepository,
            \Mockery::mock(ResultsJobRepository::class),
            HttpMockedWorkerManagerClientFactory::create()
        );

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeResultsJobHasEndState(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJob = $resultsJobFactory->createRandomForJob($job);
        $resultsJob->setState('end');
        $resultsJob->setEndState('end');
        $resultsJobRepository->save($resultsJob);

        $handler = $this->createHandler(
            $jobRepository,
            $resultsJobRepository,
            HttpMockedWorkerManagerClientFactory::create()
        );

        $message = new TerminateMachineMessage(self::$apiToken, $job->id);

        $handler($message);

        $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerManagerException = new \Exception('Failed to terminate machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($jobRepository, $resultsJobRepository, $workerManagerClient);

        $message = new TerminateMachineMessage(self::$apiToken, $job->id);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::create(
            $job->id,
            'create/requested',
            'pre_active',
            [],
            false,
            false,
            false,
            false,
        );

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForWorkerManagerMachine($machine),
        ]);

        $handler = $this->createHandler($jobRepository, $resultsJobRepository, $workerManagerClient);

        $handler(new TerminateMachineMessage(self::$apiToken, $job->id));

        $events = $this->eventRecorder->all(MachineTerminationRequestedEvent::class);
        $event = $events[0] ?? null;
        self::assertInstanceOf(MachineTerminationRequestedEvent::class, $event);

        self::assertSame($job->id, $event->getJobId());
    }

    protected function getHandlerClass(): string
    {
        return TerminateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return TerminateMachineMessage::class;
    }

    private function createHandler(
        JobRepository $jobRepository,
        ResultsJobRepository $resultsJobRepository,
        WorkerManagerClient $workerManagerClient,
    ): TerminateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new TerminateMachineMessageHandler(
            $jobRepository,
            $resultsJobRepository,
            $workerManagerClient,
            $eventDispatcher
        );
    }
}
