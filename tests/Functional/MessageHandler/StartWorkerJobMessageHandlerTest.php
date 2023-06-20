<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\WorkerJobStartRequestedEvent;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\MessageHandler\StartWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\WorkerClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\WorkerClient\Client as WorkerClient;
use SmartAssert\WorkerClient\Model\Job as WorkerJob;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class StartWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testInvokeNoJob(): void
    {
        $jobId = md5((string) rand());
        $handler = $this->createHandler();

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
        $this->assertNoMessagesDispatched();
    }

    public function testInvokeNoResultsJob(): void
    {
        $jobId = md5((string) rand());
        $this->createJob(
            jobId: $jobId,
        );

        $handler = $this->createHandler();

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertDispatchedMessage($message);
    }

    public function testInvokeJobSerializedSuiteStateIsFailed(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'failed',
        );
        $this->createResultsJob($job);

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
        $this->assertNoMessagesDispatched();
    }

    /**
     * @dataProvider invokeMessageIsRedispatchedDataProvider
     *
     * @param non-empty-string $serializedSuiteState
     */
    public function testInvokeMessageIsRedispatchedDueToSerializedSuiteState(string $serializedSuiteState): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: $serializedSuiteState,
        );
        $this->createResultsJob($job);

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
        $this->assertDispatchedMessage($message);
    }

    /**
     * @return array<mixed>
     */
    public function invokeMessageIsRedispatchedDataProvider(): array
    {
        return [
            'requested' => [
                'serializedSuiteState' => 'requested',
            ],
            'preparing/running' => [
                'serializedSuiteState' => 'preparing/running',
            ],
            'preparing/halted' => [
                'serializedSuiteState' => 'preparing/halted',
            ],
        ];
    }

    public function testInvokeMessageIsRedispatchedDueToNoJobResultsToken(): void
    {
        $jobId = md5((string) rand());
        $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'prepared',
        );

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

        $handler($message);

        $this->assertDispatchedMessage($message);
        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
    }

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'prepared',
            serializedSuiteId: md5((string) rand()),
        );

        $this->createResultsJob($job);

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $job->getSerializedSuiteId())
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

        try {
            $handler($message);
            self::fail(WorkerJobStartException::class . ' not thrown');
        } catch (WorkerJobStartException $e) {
            self::assertSame($serializedSuiteReadException, $e->getPreviousException());
            self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
            $this->assertNoMessagesDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'prepared',
            serializedSuiteId: md5((string) rand()),
        );

        $resultsJob = $this->createResultsJob($job);

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $job->getSerializedSuiteId())
            ->andReturn($serializedSuiteContent)
        ;

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $workerJob = \Mockery::mock(WorkerJob::class);

        $workerClient = \Mockery::mock(WorkerClient::class);
        $workerClient
            ->shouldReceive('createJob')
            ->with($job->id, $resultsJob->token, $job->maximumDurationInSeconds, $serializedSuiteContent)
            ->andReturn($workerJob)
        ;

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

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerJobStartRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new WorkerJobStartRequestedEvent(self::$apiToken, $job->id, $workerJob), $event);

        $this->assertNoMessagesDispatched();
    }

    protected function getHandlerClass(): string
    {
        return StartWorkerJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return StartWorkerJobMessage::class;
    }

    private function assertDispatchedMessage(StartWorkerJobMessage $message): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals($message, $envelope->getMessage());

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[StartWorkerJobMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $envelope->all(DelayStamp::class));
    }

    /**
     * @param non-empty-string  $jobId
     * @param ?non-empty-string $serializedSuiteState
     * @param ?non-empty-string $serializedSuiteId
     */
    private function createJob(
        string $jobId,
        ?string $serializedSuiteState = null,
        ?string $serializedSuiteId = null,
    ): Job {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            600
        );

        if (is_string($serializedSuiteState)) {
            $job = $job->setSerializedSuiteState($serializedSuiteState);
        }

        if (is_string($serializedSuiteId)) {
            $job = $job->setSerializedSuiteId($serializedSuiteId);
        }

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createResultsJob(Job $job): ResultsJob
    {
        $resultsJob = new ResultsJob($job->id, md5((string) rand()), md5((string) rand()), md5((string) rand()));

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJobRepository->save($resultsJob);

        return $resultsJob;
    }

    private function createHandler(
        ?SerializedSuiteClient $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
    ): StartWorkerJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

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

        return new StartWorkerJobMessageHandler(
            $messageBus,
            $jobRepository,
            $resultsJobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
            $eventDispatcher,
        );
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }
}
