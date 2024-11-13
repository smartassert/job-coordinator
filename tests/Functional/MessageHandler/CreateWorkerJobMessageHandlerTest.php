<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\RequestState;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\MessageHandler\CreateWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

class CreateWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(CreateWorkerJobMessageHandler::class);
        \assert($handler instanceof CreateWorkerJobMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new CreateWorkerJobMessage('api token', $jobId, '127.0.0.1');

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage('Failed to create worker-job for job "' . $jobId . '": Job entity not found');

        $handler($message);
    }

    public function testInvokeNoSerializedSuite(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $handler = $this->createHandler();

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

        $handler($message);

        $this->assertDispatchedMessage($message);
    }

    public function testInvokeNoResultsJob(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $this->createSerializedSuite($job, 'requested', false, false);

        $handler = $this->createHandler();

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

        $handler($message);

        $this->assertDispatchedMessage($message);
        self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
    }

    public function testInvokeSerializedSuiteStateIsFailed(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $this->createResultsJob($job);
        $this->createSerializedSuite($job, 'failed', false, true);

        $handler = self::getContainer()->get(CreateWorkerJobMessageHandler::class);
        \assert($handler instanceof CreateWorkerJobMessageHandler);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $abortedWorkerJobCreateRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => 'worker-job/create',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(0, $abortedWorkerJobCreateRemoteRequests);

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));

        self::assertEquals(
            [
                new MessageNotHandleableEvent($message),
            ],
            $this->eventRecorder->all(MessageNotHandleableEvent::class)
        );
        $this->assertNoStartWorkerJobMessageDispatched();

        $abortedWorkerJobCreateRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => 'worker-job/create',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(1, $abortedWorkerJobCreateRemoteRequests);
    }

    /**
     * @param non-empty-string $serializedSuiteState
     */
    #[DataProvider('invokeMessageIsRedispatchedDataProvider')]
    public function testInvokeMessageIsRedispatchedDueToSerializedSuiteState(
        string $serializedSuiteState,
        bool $isPrepared,
        bool $hasEndState,
    ): void {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $this->createSerializedSuite($job, $serializedSuiteState, $isPrepared, $hasEndState);
        $this->createResultsJob($job);

        $handler = self::getContainer()->get(CreateWorkerJobMessageHandler::class);
        \assert($handler instanceof CreateWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
        $this->assertDispatchedMessage($message);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeMessageIsRedispatchedDataProvider(): array
    {
        return [
            'requested' => [
                'serializedSuiteState' => 'requested',
                'isPrepared' => false,
                'hasEndState' => false,
            ],
            'preparing/running' => [
                'serializedSuiteState' => 'preparing/running',
                'isPrepared' => false,
                'hasEndState' => false,
            ],
            'preparing/halted' => [
                'serializedSuiteState' => 'preparing/halted',
                'isPrepared' => false,
                'hasEndState' => false,
            ],
        ];
    }

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', true, true);
        $this->createResultsJob($job);

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->getId())
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($serializedSuiteReadException, $e->getPreviousException());
            self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
            $this->assertNoStartWorkerJobMessageDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->id);

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', true, true);
        $this->createResultsJob($job);

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->getId())
            ->andReturn($serializedSuiteContent)
        ;

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $workerJob = WorkerClientJobFactory::createRandom();

        $workerClient = HttpMockedWorkerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $workerJob->reference->label,
                'reference' => $workerJob->reference->reference,
                'maximum_duration_in_seconds' => $workerJob->maximumDurationInSeconds,
                'sources' => [],
                'test_paths' => [],
                'references' => [],
                'tests' => [],
            ])),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with('http://' . $machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
            workerClientFactory: $workerClientFactory,
        );

        $message = new CreateWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

        $handler($message);

        $events = $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new CreateWorkerJobRequestedEvent($job->id, $machineIpAddress, $workerJob),
            $event
        );

        $this->assertNoStartWorkerJobMessageDispatched();
    }

    protected function getHandlerClass(): string
    {
        return CreateWorkerJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateWorkerJobMessage::class;
    }

    private function assertDispatchedMessage(CreateWorkerJobMessage $message): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals($message, $envelope->getMessage());

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[CreateWorkerJobMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $envelope->all(DelayStamp::class));
    }

    private function createJob(): Job
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createRandom();
    }

    private function createResultsJob(Job $job): void
    {
        \assert('' !== $job->id);

        $resultsJob = new ResultsJob($job->id, md5((string) rand()), md5((string) rand()), md5((string) rand()));

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJobRepository->save($resultsJob);
    }

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(
        Job $job,
        string $state,
        bool $isPrepared,
        bool $hasEndState,
    ): SerializedSuite {
        \assert('' !== $job->id);

        $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), $state, $isPrepared, $hasEndState);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }

    private function createHandler(
        ?SerializedSuiteClient $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
    ): CreateWorkerJobMessageHandler {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        if (null === $serializedSuiteClient) {
            $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        }

        if (null === $workerClientFactory) {
            $workerClientFactory = self::getContainer()->get(WorkerClientFactory::class);
            \assert($workerClientFactory instanceof WorkerClientFactory);
        }

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateWorkerJobMessageHandler(
            $jobRepository,
            $serializedSuiteRepository,
            $resultsJobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
            $eventDispatcher,
        );
    }

    private function assertNoStartWorkerJobMessageDispatched(): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);

        foreach ($envelopes as $envelope) {
            self::assertFalse($envelope->getMessage() instanceof CreateWorkerJobMessage);
        }
    }
}
