<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetSerializedSuiteReadinessHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\ReadinessAssessor\ReadinessHandlerInterface;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use Psr\Log\LoggerInterface;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        $suiteId = (string) new Ulid();
        $serializedSuiteId = (string) new Ulid();
        $message = new GetSerializedSuiteMessage(self::$apiToken, $jobId, $suiteId, $serializedSuiteId);

        $assessor = \Mockery::mock(ReadinessHandlerInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($message)
            ->andReturn(MessageHandlingReadiness::NEVER);

        $eventDispatcher = self::getContainer()->get(\Psr\EventDispatcher\EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $handler = new GetSerializedSuiteMessageHandler(
            $assessor,
            \Mockery::mock(SerializedSuiteClient::class),
            $eventDispatcher,
            $messageBus,
            $logger,
        );

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $job = $this->createJob();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuite = new SerializedSuite(
            $job->getId(),
            md5((string) rand()),
            'requested',
            new MetaState(false, false),
        );
        $serializedSuiteRepository->save($serializedSuite);
        \assert('' !== $serializedSuite->id);

        $serializedSuiteClientException = new \Exception(md5((string) rand()));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClient::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(RemoteJobActionException::class);
        self::expectExceptionMessage(sprintf(
            'Failed to retrieve serialized-suite for job "%s": %s',
            $job->getId(),
            $serializedSuiteClientException->getMessage()
        ));

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $assessor = self::getContainer()->get(GetSerializedSuiteReadinessHandler::class);
        \assert($assessor instanceof ReadinessHandlerInterface);

        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $handler = new GetSerializedSuiteMessageHandler(
            $assessor,
            $serializedSuiteClient,
            $eventDispatcher,
            $messageBus,
            $logger,
        );

        $message = new GetSerializedSuiteMessage(
            self::$apiToken,
            $job->getId(),
            $job->getSuiteId(),
            $serializedSuite->id
        );

        ($handler)($message);

        self::assertEquals([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
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
}
