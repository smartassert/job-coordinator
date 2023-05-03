<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteStateMessage;
use App\MessageDispatcher\SerializedSuiteStateChangeCheckMessageDispatcher;
use App\MessageHandler\GetSerializedSuiteStateMessageHandler;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class GetSerializedSuiteStateMessageHandlerTest extends WebTestCase
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
        $handler = self::getContainer()->get(GetSerializedSuiteStateMessageHandler::class);
        self::assertInstanceOf(GetSerializedSuiteStateMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeNoJob(): void
    {
        $this->createMessageAndHandleMessage($this->apiToken, md5((string) rand()));

        $this->assertNoMessagesDispatched();
    }

    /**
     * @dataProvider serializedSuiteEndStateDataProvider
     *
     * @param non-empty-string $jobSerializedSuiteState
     */
    public function testInvokeJobSerializedSuiteStateIsEndState(string $jobSerializedSuiteState): void
    {
        $job = $this->createJob($jobSerializedSuiteState, md5((string) rand()));

        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        $this->createMessageAndHandleMessage($this->apiToken, $serializedSuiteId);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @return array<mixed>
     */
    public function serializedSuiteEndStateDataProvider(): array
    {
        return [
            'prepared' => [
                'state' => 'prepared',
            ],
            'failed' => [
                'state' => 'failed',
            ],
        ];
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob(md5((string) rand()), md5((string) rand()));

        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        $serializedSuiteClientException = new \Exception(md5((string) rand()));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with($this->apiToken, $serializedSuiteId)
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(SerializedSuiteRetrievalException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized suite "%s": %s',
            $serializedSuiteId,
            $serializedSuiteClientException->getMessage()
        ));

        $this->createMessageAndHandleMessage($this->apiToken, $serializedSuiteId, $serializedSuiteClient);
    }

    public function testInvokeNoStateChangeNotEndState(): void
    {
        $job = $this->createJob(md5((string) rand()), md5((string) rand()));

        $serializedSuiteState = $job->getSerializedSuiteState();
        \assert(is_string($serializedSuiteState));

        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        $serializedSuite = new SerializedSuite(
            $serializedSuiteId,
            md5((string) rand()),
            [],
            $serializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with($this->apiToken, $job->getSerializedSuiteId())
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage($this->apiToken, $serializedSuiteId, $serializedSuiteClient);

        self::assertSame($serializedSuiteState, $job->getSerializedSuiteState());
        self::assertDispatchedMessage($this->apiToken, $serializedSuiteId);
    }

    public function testInvokeHasStateChangeNotEndState(): void
    {
        $job = $this->createJob(md5((string) rand()), md5((string) rand()));
        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        $newSerializedSuiteState = md5((string) rand());

        $serializedSuite = new SerializedSuite(
            $serializedSuiteId,
            md5((string) rand()),
            [],
            $newSerializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with($this->apiToken, $job->getSerializedSuiteId())
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage($this->apiToken, $serializedSuiteId, $serializedSuiteClient);

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());
        self::assertDispatchedMessage($this->apiToken, $serializedSuiteId);
    }

    /**
     * @dataProvider serializedSuiteEndStateDataProvider
     *
     * @param non-empty-string $serializedSuiteState
     */
    public function testInvokeHasStateChangeIsEndState(string $serializedSuiteState): void
    {
        $job = $this->createJob($serializedSuiteState, md5((string) rand()));
        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        $serializedSuite = new SerializedSuite(
            $serializedSuiteId,
            md5((string) rand()),
            [],
            $serializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with($this->apiToken, $serializedSuiteId)
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage($this->apiToken, $serializedSuiteId, $serializedSuiteClient);

        self::assertSame($serializedSuiteState, $job->getSerializedSuiteState());
        self::assertNoMessagesDispatched();
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $serializedSuiteId
     */
    private function createMessageAndHandleMessage(
        string $authenticationToken,
        string $serializedSuiteId,
        ?SerializedSuiteClient $serializedSuiteClient = null,
    ): void {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $messageDispatcher = self::getContainer()->get(SerializedSuiteStateChangeCheckMessageDispatcher::class);
        \assert($messageDispatcher instanceof SerializedSuiteStateChangeCheckMessageDispatcher);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = $serializedSuiteClient instanceof SerializedSuiteClient
            ? $serializedSuiteClient
            : \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteStateMessageHandler(
            $jobRepository,
            $serializedSuiteClient,
            $messageDispatcher
        );

        $message = new GetSerializedSuiteStateMessage($authenticationToken, $serializedSuiteId);

        ($handler)($message);
    }

    private function assertNoMessagesDispatched(): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $serializedSuiteId
     */
    private function assertDispatchedMessage(string $authenticationToken, string $serializedSuiteId): void
    {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new GetSerializedSuiteStateMessage($authenticationToken, $serializedSuiteId),
            $envelope->getMessage()
        );
    }

    /**
     * @param non-empty-string $serializedSuiteState
     * @param non-empty-string $serializedSuiteId
     */
    private function createJob(
        string $serializedSuiteState,
        string $serializedSuiteId,
    ): Job {
        $job = new Job(
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            rand(1, 1000),
        );
        $job->setSerializedSuiteState($serializedSuiteState);
        $job->setSerializedSuiteId($serializedSuiteId);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }
}
