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

        $handler = new StartWorkerJobMessageHandler(
            \Mockery::mock(StartWorkerJobMessageDispatcher::class),
            $jobRepository,
            \Mockery::mock(SerializedSuiteClient::class),
            \Mockery::mock(WorkerClientFactory::class),
        );

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeJobSerializedSuiteStateIsFailed(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobId = md5((string) rand());
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );
        $job->setSerializedSuiteState('failed');
        $jobRepository->add($job);

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
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobId = md5((string) rand());
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );
        $job->setSerializedSuiteState($serializedSuiteState);
        $jobRepository->add($job);

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
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobId = md5((string) rand());
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );
        $job->setSerializedSuiteState('prepared');
        $jobRepository->add($job);

        $messageDispatcher = self::getContainer()->get(StartWorkerJobMessageDispatcher::class);
        \assert($messageDispatcher instanceof StartWorkerJobMessageDispatcher);

        $workerClientFactory = self::getContainer()->get(WorkerClientFactory::class);
        \assert($workerClientFactory instanceof WorkerClientFactory);

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with($this->apiToken, $job->serializedSuiteId)
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = new StartWorkerJobMessageHandler(
            $messageDispatcher,
            $jobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
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
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobId = md5((string) rand());
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );
        $job->setSerializedSuiteState('prepared');
        $jobRepository->add($job);

        $messageDispatcher = self::getContainer()->get(StartWorkerJobMessageDispatcher::class);
        \assert($messageDispatcher instanceof StartWorkerJobMessageDispatcher);

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

        $handler = new StartWorkerJobMessageHandler(
            $messageDispatcher,
            $jobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
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
}
