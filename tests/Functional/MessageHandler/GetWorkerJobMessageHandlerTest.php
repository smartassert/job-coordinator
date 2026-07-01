<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\WorkerJobRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetWorkerJobMessage;
use App\MessageHandler\GetWorkerJobMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Generator\BoolValue;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\Ip;
use App\Tests\Services\Generator\StringValue;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use SmartAssert\WorkerClient\Model\MetaState;
use Symfony\Component\Messenger\MessageBusInterface;

class GetWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $message = new GetWorkerJobMessage($jobId, '127.0.0.1');
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $handler = $this->createHandler(\Mockery::mock(WorkerClientFactory::class), $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeWorkerClientThrowsException(): void
    {
        $jobId = Id::generate();
        $machineIpAddress = Ip::random();
        $message = new GetWorkerJobMessage($jobId, $machineIpAddress);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerClientException = new \Exception('Failed to get worker state');
        $workerClient = HttpMockedWorkerClientFactory::create([$workerClientException]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
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
        $jobId = Id::generate();
        $machineIpAddress = Ip::random();
        $message = new GetWorkerJobMessage($jobId, $machineIpAddress);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $retrievedWorkerState = new ApplicationState(
            new ComponentState(
                StringValue::random(),
                new MetaState(BoolValue::random(), BoolValue::random(), BoolValue::random())
            ),
            new ComponentState(
                StringValue::random(),
                new MetaState(BoolValue::random(), BoolValue::random(), BoolValue::random())
            ),
            new ComponentState(
                StringValue::random(),
                new MetaState(BoolValue::random(), BoolValue::random(), BoolValue::random())
            ),
            new ComponentState(
                StringValue::random(),
                new MetaState(BoolValue::random(), BoolValue::random(), BoolValue::random())
            ),
        );

        $workerClient = HttpMockedWorkerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'application' => [
                    'state' => $retrievedWorkerState->applicationState->state,
                    'is_end_state' => $retrievedWorkerState->applicationState->metaState->ended,
                    'meta_state' => [
                        'pending' => $retrievedWorkerState->applicationState->metaState->pending,
                        'ended' => $retrievedWorkerState->applicationState->metaState->ended,
                        'succeeded' => $retrievedWorkerState->applicationState->metaState->succeeded,
                    ],
                ],
                'compilation' => [
                    'state' => $retrievedWorkerState->compilationState->state,
                    'is_end_state' => $retrievedWorkerState->compilationState->metaState->ended,
                    'meta_state' => [
                        'pending' => $retrievedWorkerState->compilationState->metaState->pending,
                        'ended' => $retrievedWorkerState->compilationState->metaState->ended,
                        'succeeded' => $retrievedWorkerState->compilationState->metaState->succeeded,
                    ],
                ],
                'execution' => [
                    'state' => $retrievedWorkerState->executionState->state,
                    'is_end_state' => $retrievedWorkerState->executionState->metaState->ended,
                    'meta_state' => [
                        'pending' => $retrievedWorkerState->executionState->metaState->pending,
                        'ended' => $retrievedWorkerState->executionState->metaState->ended,
                        'succeeded' => $retrievedWorkerState->executionState->metaState->succeeded,
                    ],
                ],
                'event_delivery' => [
                    'state' => $retrievedWorkerState->eventDeliveryState->state,
                    'is_end_state' => $retrievedWorkerState->eventDeliveryState->metaState->ended,
                    'meta_state' => [
                        'pending' => $retrievedWorkerState->eventDeliveryState->metaState->pending,
                        'ended' => $retrievedWorkerState->eventDeliveryState->metaState->ended,
                        'succeeded' => $retrievedWorkerState->eventDeliveryState->metaState->succeeded,
                    ],
                ],
            ])),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory, $assessor);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerJobRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new WorkerJobRetrievedEvent($jobId, $machineIpAddress, $retrievedWorkerState),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return GetWorkerJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetWorkerJobMessage::class;
    }

    private function createHandler(
        WorkerClientFactory $workerClientFactory,
        ReadinessAssessorInterface $readinessAssessor,
    ): GetWorkerJobMessageHandler {
        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        return new GetWorkerJobMessageHandler(
            $readinessAssessor,
            $workerClientFactory,
            $eventDispatcher,
            $messageBus,
            $logger,
        );
    }
}
