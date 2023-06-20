<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Event\WorkerJobStartRequestedEvent;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\MessageHandler\StartWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
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

    public function testInvokeNoSerializedSuite(): void
    {
        $job = $this->createJob();

        $handler = $this->createHandler();

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

        $handler($message);

        $this->assertDispatchedMessage($message);
    }

    public function testInvokeNoResultsJob(): void
    {
        $job = $this->createJob();
        $this->createSerializedSuite($job, 'requested');

        $handler = $this->createHandler();

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

        $handler($message);

        $this->assertDispatchedMessage($message);
        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
    }

    public function testInvokeSerializedSuiteStateIsFailed(): void
    {
        $job = $this->createJob();
        $this->createResultsJob($job);
        $this->createSerializedSuite($job, 'failed');

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, md5((string) rand()));

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
        $job = $this->createJob();
        $this->createSerializedSuite($job, $serializedSuiteState);
        $this->createResultsJob($job);

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

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

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $job = $this->createJob();

        $serializedSuite = $this->createSerializedSuite($job, 'prepared');
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

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

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
        $job = $this->createJob();

        $serializedSuite = $this->createSerializedSuite($job, 'prepared');
        $resultsJob = $this->createResultsJob($job);

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->getId())
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

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

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

    private function createJob(): Job
    {
        $job = new Job(
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );

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

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(Job $job, string $state): SerializedSuite
    {
        $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), $state);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }

    private function createHandler(
        ?SerializedSuiteClient $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
    ): StartWorkerJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

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

        return new StartWorkerJobMessageHandler(
            $messageBus,
            $jobRepository,
            $serializedSuiteRepository,
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
