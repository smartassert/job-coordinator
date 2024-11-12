<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\SerializedSuite;
use App\Enum\RequestState;
use App\Event\MessageNotHandleableEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\MessageHandlerTargetEntityNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
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
        self::expectExceptionMessage(
            'Failed to retrieve serialized-suite for job "' . $jobId . '": Job entity not found'
        );

        $handler($message);
    }

    public function testInvokeSerializedSuiteNotFound(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $handler = self::getContainer()->get(GetSerializedSuiteMessageHandler::class);
        \assert($handler instanceof GetSerializedSuiteMessageHandler);

        $serializedSuiteId = (string) new Ulid();
        \assert('' !== $serializedSuiteId);

        $message = new GetSerializedSuiteMessage('api token', $job->id, $serializedSuiteId);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $remoteRequest = $remoteRequestRepository->find(
            RemoteRequest::generateId($job->id, $message->getRemoteRequestType(), $message->getIndex())
        );
        self::assertNull($remoteRequest);

        $exception = null;

        try {
            $handler($message);
            self::fail(MessageHandlerTargetEntityNotFoundException::class . ' not thrown');
        } catch (MessageHandlerTargetEntityNotFoundException $exception) {
        }

        self::assertInstanceOf(MessageHandlerTargetEntityNotFoundException::class, $exception);

        $remoteRequest = $remoteRequestRepository->find(
            RemoteRequest::generateId($job->id, $message->getRemoteRequestType(), $message->getIndex())
        );

        self::assertInstanceOf(RemoteRequest::class, $remoteRequest);
        self::assertSame(RequestState::ABORTED, $remoteRequest->getState());
    }

    public function testInvokeSerializedSuiteStateIsEndState(): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, 'failed', true, true);
        $serializedSuiteId = $serializedSuite->getId();
        \assert(null !== $serializedSuiteId);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $abortedSerializedSuiteRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => 'serialized-suite/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(0, $abortedSerializedSuiteRetrieveRemoteRequests);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteRepository,
            $serializedSuiteClient,
            $eventDispatcher
        );
        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteId);

        ($handler)($message);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));

        self::assertEquals(
            [
                new MessageNotHandleableEvent($message),
            ],
            $this->eventRecorder->all(MessageNotHandleableEvent::class)
        );

        $abortedSerializedSuiteRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => 'serialized-suite/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(1, $abortedSerializedSuiteRetrieveRemoteRequests);
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, 'requested', false, false);
        $serializedSuiteId = $serializedSuite->getId();
        \assert(null !== $serializedSuiteId);

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

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteRepository,
            $serializedSuiteClient,
            $eventDispatcher
        );
        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteId);

        ($handler)($message);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    public function testInvokeNotEndState(): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, md5((string) rand()), false, false);
        $serializedSuiteId = $serializedSuite->getId();
        \assert(null !== $serializedSuiteId);

        $serializedSuite = new SerializedSuiteModel(
            $serializedSuiteId,
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

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteRepository,
            $serializedSuiteClient,
            $eventDispatcher
        );

        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuite->getId());

        ($handler)($message);

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
