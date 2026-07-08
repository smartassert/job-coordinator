<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetSerializedSuiteMessage;
use App\MessageHandler\GetSerializedSuiteMessageHandler;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetSerializedSuiteReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\SerializedSuiteRepository;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GetSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $suiteId = Id::generate();
        $serializedSuiteId = Id::generate();
        $message = new GetSerializedSuiteMessage(self::$apiToken, $jobId, $suiteId, $serializedSuiteId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $eventDispatcher = self::getContainer()->get(\Psr\EventDispatcher\EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $handler = new GetSerializedSuiteMessageHandler(
            $assessor,
            $messageStateMutator,
            \Mockery::mock(SerializedSuiteClientInterface::class),
            $eventDispatcher,
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
            Id::generate(),
            'requested',
            new MetaState(false, false, true),
        );
        $serializedSuiteRepository->save($serializedSuite);
        \assert('' !== $serializedSuite->id);

        $serializedSuiteClientException = new \Exception(StringValue::random());

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('get')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andThrow($serializedSuiteClientException)
        ;

        self::expectException(RemoteJobActionException::class);
        self::expectExceptionMessageIsOrContains(sprintf(
            'Failed to retrieve serialized-suite for job "%s": %s',
            $job->getId(),
            $serializedSuiteClientException->getMessage()
        ));

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $assessor = self::getContainer()->get(GetSerializedSuiteReadinessAssessor::class);
        \assert($assessor instanceof ReadinessAssessorInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $handler = new GetSerializedSuiteMessageHandler(
            $assessor,
            $messageStateMutator,
            $serializedSuiteClient,
            $eventDispatcher,
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
