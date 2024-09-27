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
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

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
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);
        $handler = $this->createHandler();

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

        self::assertEquals([], $this->eventRecorder->all(WorkerJobStartRequestedEvent::class));
        $this->assertNoStartWorkerJobMessageDispatched();
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
        $this->assertNoStartWorkerJobMessageDispatched();
    }

    /**
     * @param non-empty-string $serializedSuiteState
     */
    #[DataProvider('invokeMessageIsRedispatchedDataProvider')]
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
    public static function invokeMessageIsRedispatchedDataProvider(): array
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
            $this->assertNoStartWorkerJobMessageDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();

        $serializedSuite = $this->createSerializedSuite($job, 'prepared');
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

        $message = new StartWorkerJobMessage(self::$apiToken, $job->id, $machineIpAddress);

        $handler($message);

        $events = $this->eventRecorder->all(WorkerJobStartRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new WorkerJobStartRequestedEvent($job->id, $machineIpAddress, $workerJob),
            $event
        );

        $this->assertNoStartWorkerJobMessageDispatched();
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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createRandom();
    }

    private function createResultsJob(Job $job): void
    {
        $resultsJob = new ResultsJob($job->id, md5((string) rand()), md5((string) rand()), md5((string) rand()));

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJobRepository->save($resultsJob);
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

    private function assertNoStartWorkerJobMessageDispatched(): void
    {
        $envelopes = $this->messengerTransport->getSent();
        self::assertIsArray($envelopes);

        foreach ($envelopes as $envelope) {
            self::assertFalse($envelope->getMessage() instanceof StartWorkerJobMessage);
        }
    }
}
