<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\MachineIsReadyEvent;
use App\Message\IsWorkerReadyMessage;
use App\MessageHandler\IsWorkerReadyMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\WorkerComponentStateRepository;
use App\Services\MessageStateMutator;
use App\Services\UnhandleableMessageHandler;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SmartAssert\ServiceClient\Exception\CurlException;

class IsWorkerReadyMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();

        $message = new IsWorkerReadyMessage($jobId, '127.0.0.1');
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

    public function testInvokeNotYetHandleable(): void
    {
        $jobId = Id::generate();
        $authenticationToken = StringValue::random();

        $message = new IsWorkerReadyMessage($jobId, '127.0.0.1');
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $handler = $this->createHandler(\Mockery::mock(WorkerClientFactory::class), $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::HALTED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeWorkerClientThrowsException(): void
    {
        $jobId = Id::generate();
        $machineIpAddress = '127.0.0.1';

        $message = new IsWorkerReadyMessage($jobId, $machineIpAddress);
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

        $handler($message);

        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeSuccessIsNotReady(): void
    {
        $jobId = Id::generate();
        $machineIpAddress = '127.0.0.1';

        $message = new IsWorkerReadyMessage($jobId, $machineIpAddress);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerClient = HttpMockedWorkerClientFactory::create([
            new CurlException(
                new Request('GET', 'https://example.com/'),
                7,
                'Failed to connect() to host or proxy.'
            ),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory, $assessor);

        $handler($message);

        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeSuccessIsReady(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $machineIpAddress = '127.0.0.1';

        $message = new IsWorkerReadyMessage($job->getId(), $machineIpAddress);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerClient = HttpMockedWorkerClientFactory::create([
            new Response(
                200,
                ['content-type' => 'application/json'],
                (string) json_encode([
                    'application' => [
                        'state' => 'awaiting-job',
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                    'compilation' => [
                        'state' => 'awaiting',
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                    'execution' => [
                        'state' => 'awaiting',
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => 'awaiting',
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                    ],
                ])
            ),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory, $assessor);

        $handler($message);

        $events = $this->eventRecorder->all(MachineIsReadyEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new MachineIsReadyEvent($job->getId(), $machineIpAddress),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return IsWorkerReadyMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return IsWorkerReadyMessage::class;
    }

    private function createHandler(
        WorkerClientFactory $workerClientFactory,
        ReadinessAssessorInterface $readinessAssessor,
    ): IsWorkerReadyMessageHandler {
        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $unhandleableMessageHandler = self::getContainer()->get(UnhandleableMessageHandler::class);
        \assert($unhandleableMessageHandler instanceof UnhandleableMessageHandler);

        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        return new IsWorkerReadyMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $unhandleableMessageHandler,
            $workerClientFactory,
            $eventDispatcher,
            $logger,
        );
    }
}
