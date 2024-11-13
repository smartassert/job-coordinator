<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

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
use App\Model\JobInterface;
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
        \assert('' !== $job->getId());

        $handler = self::getContainer()->get(GetSerializedSuiteMessageHandler::class);
        \assert($handler instanceof GetSerializedSuiteMessageHandler);

        $serializedSuiteId = (string) new Ulid();
        \assert('' !== $serializedSuiteId);

        $message = new GetSerializedSuiteMessage('api token', $job->getId(), $serializedSuiteId);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $remoteRequest = $remoteRequestRepository->find(
            RemoteRequest::generateId($job->getId(), $message->getRemoteRequestType(), $message->getIndex())
        );
        self::assertNull($remoteRequest);

        try {
            $handler($message);
            self::fail(MessageHandlerTargetEntityNotFoundException::class . ' not thrown');
        } catch (MessageHandlerTargetEntityNotFoundException) {
        }

        $remoteRequest = $remoteRequestRepository->find(
            RemoteRequest::generateId($job->getId(), $message->getRemoteRequestType(), $message->getIndex())
        );

        self::assertInstanceOf(RemoteRequest::class, $remoteRequest);
        self::assertSame(RequestState::ABORTED, $remoteRequest->getState());
    }

    public function testInvokeSerializedSuiteStateIsEndState(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->getId());

        $serializedSuite = $this->createSerializedSuite($job, 'failed', true, true);
        $serializedSuiteId = $serializedSuite->getId();
        \assert(null !== $serializedSuiteId);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $abortedSerializedSuiteRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
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
        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->getId(), $serializedSuiteId);

        ($handler)($message);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));

        self::assertEquals(
            [
                new MessageNotHandleableEvent($message),
            ],
            $this->eventRecorder->all(MessageNotHandleableEvent::class)
        );

        $abortedSerializedSuiteRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => 'serialized-suite/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(1, $abortedSerializedSuiteRetrieveRemoteRequests);
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->getId());

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
            $job->getId(),
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
        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->getId(), $serializedSuiteId);

        ($handler)($message);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    public function testInvokeNotEndState(): void
    {
        $job = $this->createJob();
        \assert('' !== $job->getId());

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

        $message = new GetSerializedSuiteMessage(self::$apiToken, $job->getId(), $serializedSuite->getId());

        ($handler)($message);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteEntity = $serializedSuiteRepository->find($job->getId());
        \assert($serializedSuiteEntity instanceof SerializedSuite);

        self::assertFalse($serializedSuiteEntity->hasEndState());

        $events = $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new SerializedSuiteRetrievedEvent(self::$apiToken, $job->getId(), $serializedSuite), $event);
    }

    protected function getHandlerClass(): string
    {
        return GetSerializedSuiteMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetSerializedSuiteMessage::class;
    }

    private function createJob(): JobInterface
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createRandom();
    }

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(
        JobInterface $job,
        string $state,
        bool $isPrepared,
        bool $hasEndState
    ): SerializedSuite {
        \assert('' !== $job->getId());

        $serializedSuite = new SerializedSuite($job->getId(), md5((string) rand()), $state, $isPrepared, $hasEndState);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }
}
