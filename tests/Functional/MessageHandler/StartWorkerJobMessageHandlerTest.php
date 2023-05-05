<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\MessageDispatcher\StartWorkerJobMessageDispatcher;
use App\MessageHandler\StartWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Services\WorkerClientFactory;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use SmartAssert\WorkerClient\Client as WorkerClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class StartWorkerJobMessageHandlerTest extends WebTestCase
{
    private InMemoryTransport $messengerTransport;

    /**
     * @var non-empty-string
     */
    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $this->apiToken = $apiTokenProvider->get('user@example.com');

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        self::assertInstanceOf(StartWorkerJobMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testHandlesExpectedMessage(): void
    {
        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $invokeMethod = (new \ReflectionClass($handler::class))->getMethod('__invoke');

        $invokeMethodParameters = $invokeMethod->getParameters();
        self::assertIsArray($invokeMethodParameters);
        self::assertCount(1, $invokeMethodParameters);

        $messageParameter = $invokeMethodParameters[0];
        \assert($messageParameter instanceof \ReflectionParameter);

        $messageParameterType = $messageParameter->getType();
        \assert($messageParameterType instanceof \ReflectionNamedType);

        self::assertSame(StartWorkerJobMessage::class, $messageParameterType->getName());
    }

    public function testInvokeNoJob(): void
    {
        $jobId = md5((string) rand());

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('find')
            ->with($jobId)
            ->andReturnNull()
        ;

        $handler = $this->createHandler(
            jobRepository: $jobRepository,
        );

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeJobSerializedSuiteStateIsFailed(): void
    {
        $jobId = md5((string) rand());
        $this->createJob($jobId, 'failed');

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @dataProvider invokeMessageIsRedispatchedDataProvider
     *
     * @param non-empty-string $serializedSuiteState
     */
    public function testInvokeMessageIsRedispatched(string $serializedSuiteState): void
    {
        $jobId = md5((string) rand());
        $this->createJob($jobId, $serializedSuiteState);

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, $machineIpAddress);

        $handler($message);

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
        $jobId = md5((string) rand());
        $job = $this->createJob($jobId, 'prepared');

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with($this->apiToken, $job->serializedSuiteId)
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, $machineIpAddress);

        try {
            $handler($message);
            self::fail(WorkerJobStartException::class . ' not thrown');
        } catch (WorkerJobStartException $e) {
            self::assertSame($serializedSuiteReadException, $e->previousException);
            $this->assertNoMessagesDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob($jobId, 'prepared');

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with($this->apiToken, $job->serializedSuiteId)
            ->andReturn($serializedSuiteContent)
        ;

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $workerClient = \Mockery::mock(WorkerClient::class);
        $workerClient
            ->shouldReceive('createJob')
            ->with($job->id, $job->resultsToken, $job->maximumDurationInSeconds, $serializedSuiteContent)
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

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, $machineIpAddress);

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    private function assertDispatchedMessage(StartWorkerJobMessage $message): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals($message, $envelope->getMessage());
    }

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteState
     */
    private function createJob(string $jobId, string $serializedSuiteState): Job
    {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );
        $job->setSerializedSuiteState($serializedSuiteState);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?SerializedSuiteClient $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
    ): StartWorkerJobMessageHandler {
        $messageDispatcher = self::getContainer()->get(StartWorkerJobMessageDispatcher::class);
        \assert($messageDispatcher instanceof StartWorkerJobMessageDispatcher);

        if (null === $jobRepository) {
            $jobRepository = self::getContainer()->get(JobRepository::class);
            \assert($jobRepository instanceof JobRepository);
        }

        if (null === $serializedSuiteClient) {
            $serializedSuiteClient = self::getContainer()->get(SerializedSuiteClient::class);
            \assert($serializedSuiteClient instanceof SerializedSuiteClient);
        }

        if (null === $workerClientFactory) {
            $workerClientFactory = self::getContainer()->get(WorkerClientFactory::class);
            \assert($workerClientFactory instanceof WorkerClientFactory);
        }

        return new StartWorkerJobMessageHandler(
            $messageDispatcher,
            $jobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
        );
    }
}