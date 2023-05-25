<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\SerializedSuiteSerializedEvent;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $this->createMessageAndHandleMessage(self::$apiToken, md5((string) rand()), md5((string) rand()));

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

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuiteId);

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
            ->with(self::$apiToken, $serializedSuiteId)
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(SerializedSuiteRetrievalException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized suite "%s": %s',
            $serializedSuiteId,
            $serializedSuiteClientException->getMessage()
        ));

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuiteId, $serializedSuiteClient);
    }

    public function testInvokeNoStateChangeNotEndState(): void
    {
        $serializedSuiteState = md5((string) rand());
        $job = $this->invokeHandlerSuccessfully($serializedSuiteState, $serializedSuiteState);

        self::assertSame($serializedSuiteState, $job->getSerializedSuiteState());

        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        self::assertDispatchedMessage(self::$apiToken, $job->id, $serializedSuiteId);
    }

    public function testInvokeHasStateChangeNotEndState(): void
    {
        $newSerializedSuiteState = md5((string) rand());
        $job = $this->invokeHandlerSuccessfully(md5((string) rand()), $newSerializedSuiteState);

        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());
        self::assertDispatchedMessage(self::$apiToken, $job->id, $serializedSuiteId);
    }

    public function testInvokeHasStateChangeIsPrepared(): void
    {
        $newSerializedSuiteState = 'prepared';
        $job = $this->invokeHandlerSuccessfully(md5((string) rand()), $newSerializedSuiteState);

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());
        self::assertNoMessagesDispatched();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);

        $event = $eventRecorder->getLatest();
        self::assertInstanceOf(SerializedSuiteSerializedEvent::class, $event);
    }

    public function testInvokeHasStateChangeIsFailed(): void
    {
        $newSerializedSuiteState = 'failed';
        $job = $this->invokeHandlerSuccessfully(md5((string) rand()), $newSerializedSuiteState);

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());
        self::assertNoMessagesDispatched();
    }

    protected function getHandlerClass(): string
    {
        return GetSerializedSuiteMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetSerializedSuiteMessage::class;
    }

    /**
     * @param non-empty-string $currentSerializedSuiteState
     * @param non-empty-string $newSerializedSuiteState
     */
    private function invokeHandlerSuccessfully(
        string $currentSerializedSuiteState,
        string $newSerializedSuiteState
    ): Job {
        $job = $this->createJob($currentSerializedSuiteState, md5((string) rand()));
        $serializedSuiteId = $job->getSerializedSuiteId();
        \assert(is_string($serializedSuiteId) && '' !== $serializedSuiteId);

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
            ->with(self::$apiToken, $serializedSuiteId)
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuiteId, $serializedSuiteClient);

        return $job;
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    private function createMessageAndHandleMessage(
        string $authenticationToken,
        string $jobId,
        string $serializedSuiteId,
        ?SerializedSuiteClient $serializedSuiteClient = null,
    ): void {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = $serializedSuiteClient instanceof SerializedSuiteClient
            ? $serializedSuiteClient
            : \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteMessageHandler($jobRepository, $serializedSuiteClient, $eventDispatcher);
        $message = new GetSerializedSuiteMessage($authenticationToken, $jobId, 0, $serializedSuiteId);

        ($handler)($message);
    }

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    private function assertDispatchedMessage(
        string $authenticationToken,
        string $jobId,
        string $serializedSuiteId
    ): void {
        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertEquals(
            new GetSerializedSuiteMessage($authenticationToken, $jobId, 0, $serializedSuiteId),
            $envelope->getMessage()
        );

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[GetSerializedSuiteMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $envelope->all(DelayStamp::class));
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
