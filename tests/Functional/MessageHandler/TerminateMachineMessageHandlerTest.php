<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\MachineTerminationRequestedEvent;
use App\Exception\MachineTerminationException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use GuzzleHttp\Psr7\Response;
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

        $handler = $this->createHandler($jobRepository, HttpMockedWorkerManagerClientFactory::create());

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerManagerException = new \Exception('Failed to terminate machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($jobRepository, $workerManagerClient);

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
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::create($job->id, 'create/requested', 'pre_active', [], false);

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'id' => $machine->id,
                'state' => $machine->state,
                'state_category' => $machine->stateCategory,
                'ip_addresses' => $machine->ipAddresses,
                'has_failed_state' => $machine->hasFailedState,
            ])),
        ]);

        $handler = $this->createHandler($jobRepository, $workerManagerClient);

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

    private function createHandler(
        JobRepository $jobRepository,
        WorkerManagerClient $workerManagerClient,
    ): TerminateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new TerminateMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
    }
}
