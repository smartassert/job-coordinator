<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Exception\SerializedSuiteCreationException;
use App\Message\CreateSerializedSuiteMessage;
use App\Message\GetSerializedSuiteStateMessage;
use App\MessageHandler\CreateSerializedSuiteMessageHandler;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
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

        $message = new CreateSerializedSuiteMessage(self::$apiToken, $jobId, []);

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob();
        self::assertSame(RequestState::UNKNOWN, $job->getResultsJobRequestState());

        $serializedSuiteCreateParameters = [];

        $serializedSuiteClientException = new \Exception('Failed to create serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->suiteId, $serializedSuiteCreateParameters)
            ->andThrow($serializedSuiteClientException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $message = new CreateSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteCreateParameters);

        try {
            $handler($message);
            self::fail(SerializedSuiteCreationException::class . ' not thrown');
        } catch (SerializedSuiteCreationException $e) {
            self::assertSame($serializedSuiteClientException, $e->previousException);
            $this->assertNoMessagesDispatched();
        }

        self::assertSame(RequestState::HALTED, $job->getSerializedSuiteRequestState());
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();
        self::assertSame(RequestState::UNKNOWN, $job->getSerializedSuiteRequestState());

        $serializedSuiteParameters = [
            md5((string) rand()) => md5((string) rand()),
        ];

        $serializedSuite = new SerializedSuite(
            md5((string) rand()),
            $job->suiteId,
            $serializedSuiteParameters,
            'requested',
            null,
            null,
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->suiteId, $serializedSuiteParameters)
            ->andReturn($serializedSuite)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        self::assertNull($job->getResultsToken());

        $handler(new CreateSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteParameters));

        self::assertSame(RequestState::SUCCEEDED, $job->getSerializedSuiteRequestState());
        self::assertSame($serializedSuite->getId(), $job->getSerializedSuiteId());
        $this->assertDispatchedMessage(self::$apiToken, $serializedSuite->getId());
    }

    protected function getHandlerClass(): string
    {
        return CreateSerializedSuiteMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateSerializedSuiteMessage::class;
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

        self::assertEquals([new NonDelayedStamp()], $envelope->all(NonDelayedStamp::class));
    }

    private function createJob(): Job
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?SerializedSuiteClient $serializedSuiteClient = null,
    ): CreateSerializedSuiteMessageHandler {
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

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        return new CreateSerializedSuiteMessageHandler($jobRepository, $serializedSuiteClient, $messageBus);
    }
}