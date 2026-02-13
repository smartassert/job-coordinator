<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use SmartAssert\SourcesClient\SerializedSuiteClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Ulid;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        $suiteId = (string) new Ulid();
        $serializedSuiteId = (string) new Ulid();
        $message = new GetSerializedSuiteMessage(self::$apiToken, $jobId, $suiteId, $serializedSuiteId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->withArgs(function (RemoteRequestType $type, string $passedJobId) use ($message) {
                self::assertTrue($type->equals($message->getRemoteRequestType()));
                self::assertSame($passedJobId, $message->getJobId());

                return true;
            })
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $eventDispatcher = self::getContainer()->get(\Psr\EventDispatcher\EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetSerializedSuiteMessageHandler(
            \Mockery::mock(SerializedSuiteClient::class),
            $eventDispatcher,
            $assessor,
        );

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(SerializedSuiteRetrievedEvent::class));
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

        $assessor = self::getContainer()->get(ReadinessAssessorInterface::class);
        \assert($assessor instanceof ReadinessAssessorInterface);

        $handler = new GetSerializedSuiteMessageHandler($serializedSuiteClient, $eventDispatcher, $assessor);
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
