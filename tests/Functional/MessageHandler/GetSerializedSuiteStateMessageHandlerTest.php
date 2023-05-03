<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteStateMessage;
use App\MessageHandler\GetSerializedSuiteStateMessageHandler;
use App\Repository\JobRepository;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class GetSerializedSuiteStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $this->createMessageAndHandleMessage(self::$apiToken, md5((string) rand()));

        $this->assertNoMessagesDispatched();
    }

    /**
     * @dataProvider serializedSuiteEndStateDataProvider
     *
     * @param non-empty-string $jobSerializedSuiteState
     */
    public function testInvokeJobSerializedSuiteStateIsEndState(string $jobSerializedSuiteState): void
    {
        $job = $this->createJob($jobSerializedSuiteState);

        $this->createMessageAndHandleMessage(self::$apiToken, $job->serializedSuiteId);

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
        $job = $this->createJob(md5((string) rand()));

        $serializedSuiteClientException = new \Exception(md5((string) rand()));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $job->serializedSuiteId)
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(SerializedSuiteRetrievalException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized suite "%s": %s',
            $job->serializedSuiteId,
            $serializedSuiteClientException->getMessage()
        ));

        $this->createMessageAndHandleMessage(self::$apiToken, $job->serializedSuiteId, $serializedSuiteClient);
    }

    public function testInvokeNoStateChangeNotEndState(): void
    {
        $job = $this->createJob(md5((string) rand()));
        $serializedSuiteState = $job->getSerializedSuiteState();
        \assert(is_string($serializedSuiteState));

        $serializedSuite = new SerializedSuite(
            $job->serializedSuiteId,
            md5((string) rand()),
            [],
            $serializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $job->serializedSuiteId)
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage(self::$apiToken, $job->serializedSuiteId, $serializedSuiteClient);

        self::assertSame($serializedSuiteState, $job->getSerializedSuiteState());
        self::assertDispatchedMessage(self::$apiToken, $job->serializedSuiteId);
    }

    public function testInvokeHasStateChangeNotEndState(): void
    {
        $job = $this->createJob(md5((string) rand()));
        $newSerializedSuiteState = md5((string) rand());

        $serializedSuite = new SerializedSuite(
            $job->serializedSuiteId,
            md5((string) rand()),
            [],
            $newSerializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $job->serializedSuiteId)
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage(self::$apiToken, $job->serializedSuiteId, $serializedSuiteClient);

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());
        self::assertDispatchedMessage(self::$apiToken, $job->serializedSuiteId);
    }

    /**
     * @dataProvider serializedSuiteEndStateDataProvider
     *
     * @param non-empty-string $serializedSuiteState
     */
    public function testInvokeHasStateChangeIsEndState(string $serializedSuiteState): void
    {
        $job = $this->createJob($serializedSuiteState);

        $serializedSuite = new SerializedSuite(
            $job->serializedSuiteId,
            md5((string) rand()),
            [],
            $serializedSuiteState,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $job->serializedSuiteId)
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage(self::$apiToken, $job->serializedSuiteId, $serializedSuiteClient);

        self::assertSame($serializedSuiteState, $job->getSerializedSuiteState());
        self::assertNoMessagesDispatched();
    }

    protected function getHandlerClass(): string
    {
        return GetSerializedSuiteStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetSerializedSuiteStateMessage::class;
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

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = $serializedSuiteClient instanceof SerializedSuiteClient
            ? $serializedSuiteClient
            : \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteStateMessageHandler($jobRepository, $serializedSuiteClient, $messageBus);
        $message = new GetSerializedSuiteStateMessage($authenticationToken, $serializedSuiteId);

        ($handler)($message);
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

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[GetSerializedSuiteStateMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $envelope->all(DelayStamp::class));
    }

    /**
     * @param non-empty-string $serializedSuiteState
     */
    private function createJob(string $serializedSuiteState): Job
    {
        $job = new Job(
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            rand(1, 1000),
        );
        $job->setSerializedSuiteState($serializedSuiteState);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }
}
