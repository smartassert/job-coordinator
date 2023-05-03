<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Exception\WorkerJobStartException;
use App\Message\StartWorkerJobMessage;
use App\MessageHandler\StartWorkerJobMessageHandler;
use App\Repository\JobRepository;
use App\Services\WorkerClientFactory;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\WorkerClient\Client as WorkerClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class StartWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
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

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeJobSerializedSuiteStateIsFailed(): void
    {
        $jobId = md5((string) rand());
        $this->createJob(
            jobId: $jobId,
            resultsToken: 'results token',
            serializedSuiteState: 'failed',
        );

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, md5((string) rand()));

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeNoJobResultsToken(): void
    {
        $jobId = md5((string) rand());
        $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'failed',
        );

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, md5((string) rand()));

        $handler($message);

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
        $this->createJob(
            jobId: $jobId,
            resultsToken: 'results token',
            serializedSuiteState: $serializedSuiteState,
        );

        $handler = self::getContainer()->get(StartWorkerJobMessageHandler::class);
        \assert($handler instanceof StartWorkerJobMessageHandler);

        $machineIpAddress = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

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

        $message = new StartWorkerJobMessage($this->apiToken, $jobId, $machineIpAddress);

        $handler($message);

        $this->assertDispatchedMessage($message);
    }

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            resultsToken: 'results token',
            serializedSuiteState: 'prepared',
        );

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $job->serializedSuiteId)
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
            self::assertSame($serializedSuiteReadException, $e->previousException);
            $this->assertNoMessagesDispatched();
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            resultsToken: 'results token',
            serializedSuiteState: 'prepared'
        );

        $serializedSuiteContent = md5((string) rand());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $job->serializedSuiteId)
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

        $message = new StartWorkerJobMessage(self::$apiToken, $jobId, $machineIpAddress);

        $handler($message);

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
        $envelopes = $this->messengerTransport->get();
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
     * @param ?non-empty-string $resultsToken
     * @param ?non-empty-string $serializedSuiteState
     */
    private function createJob(
        string $jobId,
        ?string $resultsToken = null,
        ?string $serializedSuiteState = null,
    ): Job {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            600
        );

        if (is_string($resultsToken)) {
            $job = $job->setResultsToken($resultsToken);
        }

        if (is_string($serializedSuiteState)) {
            $job = $job->setSerializedSuiteState($serializedSuiteState);
        }

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
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

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
            $messageBus,
            $jobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
        );
    }
}
