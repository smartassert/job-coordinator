<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineTerminationRequestedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class TerminateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotYetHandleable(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($workerManagerClient, $assessor);
        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::EVENTUALLY, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($workerManagerClient, $assessor);
        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerManagerException = new \Exception('Failed to terminate machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($workerManagerClient, $assessor);

        $message = new TerminateMachineMessage(self::$apiToken, $jobId);

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
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $machine = MachineFactory::create(
            $jobId,
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

        $handler = $this->createHandler($workerManagerClient, $assessor);

        $handler(new TerminateMachineMessage(self::$apiToken, $jobId));

        $events = $this->eventRecorder->all(MachineTerminationRequestedEvent::class);
        $event = $events[0] ?? null;
        self::assertInstanceOf(MachineTerminationRequestedEvent::class, $event);

        self::assertSame($jobId, $event->getJobId());
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
        WorkerManagerClient $workerManagerClient,
        ReadinessAssessorInterface $readinessAssessor,
    ): TerminateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new TerminateMachineMessageHandler($workerManagerClient, $eventDispatcher, $readinessAssessor);
    }
}
