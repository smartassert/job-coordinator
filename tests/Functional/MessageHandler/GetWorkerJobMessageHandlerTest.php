<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\Message\GetWorkerJobMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\MessageHandler\GetWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerClient\Model\ApplicationState;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Component\Uid\Ulid;

class GetWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(GetWorkerJobMessageHandler::class);
        \assert($handler instanceof GetWorkerJobMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new GetWorkerJobMessage($jobId, '127.0.0.1');

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage('Failed to retrieve worker-job for job "' . $jobId . '": Job not found');

        $handler($message);
    }

    public function testInvokeWorkerApplicationIsInEndState(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $applicationState = new WorkerComponentState($job->id, WorkerComponentName::APPLICATION);
        $applicationState->setState('end');
        $applicationState->setIsEndState(true);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);
        $workerComponentStateRepository->save($applicationState);

        $handler = $this->createHandler(\Mockery::mock(WorkerClientFactory::class));

        $message = new GetWorkerJobMessage($job->id, '127.0.0.1');

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    public function testInvokeWorkerClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerClientException = new \Exception('Failed to get worker state');

        $workerClient = HttpMockedWorkerClientFactory::create([$workerClientException]);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerJobMessage($job->id, $machineIpAddress);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler($workerClientFactory);

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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $message = new GetWorkerJobMessage($job->id, $machineIpAddress);

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

        $handler = $this->createHandler($workerClientFactory);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new WorkerStateRetrievedEvent($job->id, $machineIpAddress, $retrievedWorkerState), $event);
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobStateMessage::class;
    }

    private function createHandler(WorkerClientFactory $workerClientFactory): GetWorkerJobMessageHandler
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $workerComponentStateRepository = self::getContainer()->get(WorkerComponentStateRepository::class);
        \assert($workerComponentStateRepository instanceof WorkerComponentStateRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetWorkerJobMessageHandler(
            $jobRepository,
            $workerComponentStateRepository,
            $workerClientFactory,
            $eventDispatcher
        );
    }
}
