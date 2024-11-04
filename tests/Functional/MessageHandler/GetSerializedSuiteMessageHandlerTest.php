<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\MessageHandlerTargetEntityNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Ulid;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(GetSerializedSuiteMessageHandler::class);
        \assert($handler instanceof GetSerializedSuiteMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new GetSerializedSuiteMessage('api token', $jobId, 'serialized suite id');

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage('Failed to retrieve serialized-suite for job "' . $jobId . '": Job entity not found');

        $handler($message);
    }

    public function testInvokeNoSerializedSuite(): void
    {
        $job = $this->createJob();
        $serializedSuiteId = md5((string) rand());

        self::expectException(MessageHandlerTargetEntityNotFoundException::class);
        self::expectExceptionMessage(
            'Failed to retrieve serialized-suite for job "' . $job->id . '": SerializedSuite entity not found'
        );

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuiteId);
    }

    /**
     * @param non-empty-string $state
     */
    #[DataProvider('serializedSuiteEndStateDataProvider')]
    public function testInvokeSerializedSuiteStateIsEndState(string $state): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, $state, true, true);

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuite->getId());

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function serializedSuiteEndStateDataProvider(): array
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
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, 'requested', false, false);

        $serializedSuiteClientException = new \Exception(md5((string) rand()));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $serializedSuite->getId())
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(RemoteJobActionException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized-suite for job "%s": %s',
            $job->id,
            $serializedSuiteClientException->getMessage()
        ));

        $this->createMessageAndHandleMessage(
            self::$apiToken,
            $job->id,
            $serializedSuite->getId(),
            $serializedSuiteClient
        );

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    public function testInvokeNotEndState(): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, md5((string) rand()), false, false);

        $serializedSuite = new SerializedSuiteModel(
            $serializedSuite->getId(),
            md5((string) rand()),
            [],
            md5((string) rand()),
            false,
            false,
            null,
            null
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $serializedSuite->getId())
            ->andReturn($serializedSuite)
        ;

        $this->createMessageAndHandleMessage(
            self::$apiToken,
            $job->id,
            $serializedSuite->getId(),
            $serializedSuiteClient
        );

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteEntity = $serializedSuiteRepository->find($job->id);
        \assert($serializedSuiteEntity instanceof SerializedSuite);

        self::assertFalse($serializedSuiteEntity->hasEndState());

        $events = $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new SerializedSuiteRetrievedEvent(self::$apiToken, $job->id, $serializedSuite), $event);
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

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = $serializedSuiteClient instanceof SerializedSuiteClient
            ? $serializedSuiteClient
            : \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteRepository,
            $serializedSuiteClient,
            $eventDispatcher
        );
        $message = new GetSerializedSuiteMessage($authenticationToken, $jobId, $serializedSuiteId);

        ($handler)($message);
    }

    private function createJob(): Job
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createRandom();
    }

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(
        Job $job,
        string $state,
        bool $isPrepared,
        bool $hasEndState
    ): SerializedSuite {
        $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), $state, $isPrepared, $hasEndState);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }
}
