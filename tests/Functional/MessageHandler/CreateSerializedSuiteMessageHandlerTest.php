<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\SerializedSuite;
use App\Event\MessageNotHandleableEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageHandler\CreateSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\Uid\Ulid;

class CreateSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(CreateSerializedSuiteMessageHandler::class);
        \assert($handler instanceof CreateSerializedSuiteMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new CreateSerializedSuiteMessage('api token', $jobId, []);

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage(
            'Failed to create serialized-suite for job "' . $jobId . '": Job entity not found'
        );

        $handler($message);
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->id);

        $serializedSuiteCreateParameters = [];

        $serializedSuiteClientException = new \Exception('Failed to create serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->id, $job->suiteId, $serializedSuiteCreateParameters)
            ->andThrow($serializedSuiteClientException)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $handler = new CreateSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteClient,
            $eventDispatcher,
            $serializedSuiteRepository,
        );

        $message = new CreateSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteCreateParameters);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($serializedSuiteClientException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(SerializedSuiteCreatedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->id);
        \assert('' !== $job->suiteId);

        $serializedSuiteParameters = [
            md5((string) rand()) => md5((string) rand()),
        ];

        $serializedSuiteModel = new SerializedSuiteModel(
            $job->id,
            $job->suiteId,
            $serializedSuiteParameters,
            'requested',
            false,
            false,
            null,
            null,
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->id, $job->suiteId, $serializedSuiteParameters)
            ->andReturn($serializedSuiteModel)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $handler = new CreateSerializedSuiteMessageHandler(
            $jobRepository,
            $serializedSuiteClient,
            $eventDispatcher,
            $serializedSuiteRepository,
        );

        $handler(new CreateSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteParameters));

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteEntity = $serializedSuiteRepository->find($job->id);
        self::assertInstanceOf(SerializedSuite::class, $serializedSuiteEntity);

        self::assertSame($serializedSuiteModel->getId(), $serializedSuiteEntity->getId());

        $events = $this->eventRecorder->all(SerializedSuiteCreatedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new SerializedSuiteCreatedEvent(self::$apiToken, $job->id, $serializedSuiteModel), $event);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->id);

        $serializedSuiteParameters = [
            md5((string) rand()) => md5((string) rand()),
        ];

        $serializedSuiteRepository = \Mockery::mock(SerializedSuiteRepository::class);
        $serializedSuiteRepository
            ->shouldReceive('has')
            ->with($job->id)
            ->andReturnTrue()
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $handler = new CreateSerializedSuiteMessageHandler(
            $jobRepository,
            \Mockery::mock(SerializedSuiteClient::class),
            $eventDispatcher,
            $serializedSuiteRepository,
        );

        $message = new CreateSerializedSuiteMessage(self::$apiToken, $job->id, $serializedSuiteParameters);
        $handler($message);

        $messageNotHandleableEvents = $this->eventRecorder->all(MessageNotHandleableEvent::class);
        self::assertCount(1, $messageNotHandleableEvents);

        $messageNotHandleableEvent = $messageNotHandleableEvents[0];
        self::assertInstanceOf(MessageNotHandleableEvent::class, $messageNotHandleableEvent);
        self::assertSame($message, $messageNotHandleableEvent->message);
    }

    protected function getHandlerClass(): string
    {
        return CreateSerializedSuiteMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateSerializedSuiteMessage::class;
    }
}
