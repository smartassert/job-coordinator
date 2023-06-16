<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $this->createMessageAndHandleMessage(self::$apiToken, md5((string) rand()), md5((string) rand()));

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
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

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
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

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    /**
     * @dataProvider invokeNotEndStateDataProvider
     *
     * @param non-empty-string $currentSerializedSuiteState
     * @param non-empty-string $newSerializedSuiteState
     */
    public function testInvokeNotEndState(string $currentSerializedSuiteState, string $newSerializedSuiteState): void
    {
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

        self::assertSame($newSerializedSuiteState, $job->getSerializedSuiteState());

        $events = $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new SerializedSuiteRetrievedEvent(self::$apiToken, $job->id, $serializedSuite), $event);
    }

    /**
     * @return array<mixed>
     */
    public function invokeNotEndStateDataProvider(): array
    {
        $state = md5((string) rand());

        return [
            'no state change not end state' => [
                'currentSerializedSuiteState' => $state,
                'newSerializedSuiteState' => $state,
            ],
            'has state change not end state' => [
                'currentSerializedSuiteState' => md5((string) rand()),
                'newSerializedSuiteState' => md5((string) rand()),
            ],
        ];
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

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $serializedSuiteClient = $serializedSuiteClient instanceof SerializedSuiteClient
            ? $serializedSuiteClient
            : \Mockery::mock(SerializedSuiteClient::class);

        $handler = new GetSerializedSuiteMessageHandler($jobRepository, $serializedSuiteClient, $eventDispatcher);
        $message = new GetSerializedSuiteMessage($authenticationToken, $jobId, $serializedSuiteId);

        ($handler)($message);
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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        foreach ($jobRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $jobRepository->add($job);

        return $job;
    }
}
