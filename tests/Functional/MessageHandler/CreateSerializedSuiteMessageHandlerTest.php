<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\SerializedSuiteCreationException;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageHandler\CreateSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteClient;

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

        self::assertSame([], $this->eventRecorder->all(SerializedSuiteCreatedEvent::class));
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob();

        $serializedSuiteCreateParameters = [];

        $serializedSuiteClientException = new \Exception('Failed to create serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->id, $job->suiteId, $serializedSuiteCreateParameters)
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
            self::assertSame($serializedSuiteClientException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(SerializedSuiteCreatedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();

        $serializedSuiteParameters = [
            md5((string) rand()) => md5((string) rand()),
        ];

        $serializedSuiteModel = new SerializedSuiteModel(
            $job->id,
            $job->suiteId,
            $serializedSuiteParameters,
            'requested',
            null,
            null,
        );

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->id, $job->suiteId, $serializedSuiteParameters)
            ->andReturn($serializedSuiteModel)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
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

    protected function getHandlerClass(): string
    {
        return CreateSerializedSuiteMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateSerializedSuiteMessage::class;
    }

    private function createJob(): Job
    {
        $job = JobFactory::createRandom();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?SerializedSuiteClient $serializedSuiteClient = null,
    ): CreateSerializedSuiteMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        if (null === $jobRepository) {
            $jobRepository = self::getContainer()->get(JobRepository::class);
            \assert($jobRepository instanceof JobRepository);
        }

        if (null === $serializedSuiteClient) {
            $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        }

        return new CreateSerializedSuiteMessageHandler($jobRepository, $serializedSuiteClient, $eventDispatcher);
    }
}
