<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\WorkerStateRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\Message\GetWorkerJobMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\MessageHandler\GetWorkerJobMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Component\Uid\Ulid;

class GetWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
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

        $message = new GetWorkerJobMessage($jobId, '127.0.0.1');

        $handler = $this->createHandler(
            \Mockery::mock(WorkerClientFactory::class),
            $assessor
        );

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(WorkerStateRetrievedEvent::class));
    }

    public function testInvokeWorkerClientThrowsException(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerClientException = new \Exception('Failed to get worker state');

        $workerClient = HttpMockedWorkerClientFactory::create([$workerClientException]);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerJobMessage($jobId, $machineIpAddress);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory, $assessor);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($workerClientException, $e->getPreviousException());
            self::assertCount(0, $this->eventRecorder);
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

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerJobMessage($jobId, $machineIpAddress);

        $retrievedWorkerState = new ApplicationState(
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1)),
            new ComponentState(md5((string) rand()), (bool) rand(0, 1))
        );

        $workerClient = HttpMockedWorkerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'application' => [
                    'state' => $retrievedWorkerState->applicationState->state,
                    'is_end_state' => $retrievedWorkerState->applicationState->isEndState,
                ],
                'compilation' => [
                    'state' => $retrievedWorkerState->compilationState->state,
                    'is_end_state' => $retrievedWorkerState->compilationState->isEndState,
                ],
                'execution' => [
                    'state' => $retrievedWorkerState->executionState->state,
                    'is_end_state' => $retrievedWorkerState->executionState->isEndState,
                ],
                'event_delivery' => [
                    'state' => $retrievedWorkerState->eventDeliveryState->state,
                    'is_end_state' => $retrievedWorkerState->eventDeliveryState->isEndState,
                ],
            ])),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory, $assessor);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new WorkerStateRetrievedEvent($jobId, $machineIpAddress, $retrievedWorkerState),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobStateMessage::class;
    }

    private function createHandler(
        WorkerClientFactory $workerClientFactory,
        ReadinessAssessorInterface $readinessAssessor,
    ): GetWorkerJobMessageHandler {
        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetWorkerJobMessageHandler($workerClientFactory, $eventDispatcher, $readinessAssessor);
    }
}
