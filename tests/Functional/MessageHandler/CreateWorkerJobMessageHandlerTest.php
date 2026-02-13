<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\MessageHandler\CreateWorkerJobMessageHandler;
use App\Model\JobInterface;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
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

    public function testInvokeNotYetHandleable(): void
    {
        $jobId = (string) new Ulid();
        $message = new CreateWorkerJobMessage(self::$apiToken, $jobId, 600, md5((string) rand()));
        $assessor = ReadinessAssessorFactory::create(
            $message->getRemoteRequestType(),
            $message->getJobId(),
            MessageHandlingReadiness::EVENTUALLY
        );

        $handler = $this->createHandler(readinessAssessor: $assessor);
        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::EVENTUALLY, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        $message = new CreateWorkerJobMessage(self::$apiToken, $jobId, 600, md5((string) rand()));
        $assessor = ReadinessAssessorFactory::create(
            $message->getRemoteRequestType(),
            $message->getJobId(),
            MessageHandlingReadiness::NEVER
        );

        $handler = $this->createHandler(readinessAssessor: $assessor);
        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
    }

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $job = $this->createJob();
        $jobId = $job->getId();

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', true, true);
        $this->createResultsJob($job);

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new CreateWorkerJobMessage(
            self::$apiToken,
            $jobId,
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

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
        $jobId = $job->getId();

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', true, true);
        $this->createResultsJob($job);

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->id)
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

        $message = new CreateWorkerJobMessage(
            self::$apiToken,
            $jobId,
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

        $handler($message);

        $events = $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new CreateWorkerJobRequestedEvent($jobId, $machineIpAddress, $workerJob),
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

    private function createJob(): JobInterface
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createRandom();
    }

    private function createResultsJob(JobInterface $job): void
    {
        $resultsJob = new ResultsJob($job->getId(), md5((string) rand()), md5((string) rand()), md5((string) rand()));

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJobRepository->save($resultsJob);
    }

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(
        JobInterface $job,
        string $state,
        bool $isPrepared,
        bool $hasEndState,
    ): SerializedSuite {
        $serializedSuite = new SerializedSuite($job->getId(), md5((string) rand()), $state, $isPrepared, $hasEndState);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }

    private function createHandler(
        ?SerializedSuiteClient $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
        ?ReadinessAssessorInterface $readinessAssessor = null,
    ): CreateWorkerJobMessageHandler {
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

        if (null === $readinessAssessor) {
            $readinessAssessor = self::getContainer()->get(ReadinessAssessorInterface::class);
            \assert($readinessAssessor instanceof ReadinessAssessorInterface);
        }

        return new CreateWorkerJobMessageHandler(
            $serializedSuiteRepository,
            $resultsJobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
            $eventDispatcher,
            $readinessAssessor,
        );
    }

    private function assertNoStartWorkerJobMessageDispatched(): void
    {
        foreach ($this->messengerTransport->getSent() as $envelope) {
            self::assertFalse($envelope->getMessage() instanceof CreateWorkerJobMessage);
        }
    }
}
