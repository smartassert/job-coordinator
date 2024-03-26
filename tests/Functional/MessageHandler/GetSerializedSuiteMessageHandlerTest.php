<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\SerializedSuiteRetrievalException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Repository\JobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Ulid;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $this->createMessageAndHandleMessage(self::$apiToken, $jobId, md5((string) rand()));

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    public function testInvokeNoSerializedSuite(): void
    {
        $job = $this->createJob();
        $serializedSuiteId = md5((string) rand());

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuiteId);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
    }

    /**
     * @dataProvider serializedSuiteEndStateDataProvider
     *
     * @param non-empty-string $state
     */
    public function testInvokeSerializedSuiteStateIsEndState(string $state): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, $state);

        $this->createMessageAndHandleMessage(self::$apiToken, $job->id, $serializedSuite->getId());

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
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, 'requested');

        $serializedSuiteClientException = new \Exception(md5((string) rand()));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $serializedSuite->getId())
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(SerializedSuiteRetrievalException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized suite "%s": %s',
            $serializedSuite->getId(),
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

    /**
     * @dataProvider invokeNotEndStateDataProvider
     *
     * @param non-empty-string $currentSerializedSuiteState
     * @param non-empty-string $newSerializedSuiteState
     */
    public function testInvokeNotEndState(string $currentSerializedSuiteState, string $newSerializedSuiteState): void
    {
        $job = $this->createJob();
        $serializedSuite = $this->createSerializedSuite($job, $currentSerializedSuiteState);

        $serializedSuite = new SerializedSuiteModel(
            $serializedSuite->getId(),
            md5((string) rand()),
            [],
            $newSerializedSuiteState,
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

        self::assertSame($newSerializedSuiteState, $serializedSuiteEntity->getState());

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
    private function createSerializedSuite(Job $job, string $state): SerializedSuite
    {
        $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), $state);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        foreach ($serializedSuiteRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }
}
