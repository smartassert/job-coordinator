<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\SerializedSuiteCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateSerializedSuiteMessage;
use App\MessageHandler\CreateSerializedSuiteMessageHandler;
use App\ReadinessAssessor\CreateSerializedSuiteReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\SerializedSuiteRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\SourcesClient\Model\MetaState as SourcesClientMetaState;
use SmartAssert\SourcesClient\Model\SerializedSuite as SerializedSuiteModel;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;

class CreateSerializedSuiteMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeSerializedSuiteClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $serializedSuiteCreateParameters = [];

        $serializedSuiteClientException = new \Exception('Failed to create serialized suite');

        $notifyUrl = self::getContainer()->getParameter('notify_url');
        \assert(is_string($notifyUrl));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->getId(), $job->getSuiteId(), $notifyUrl, $serializedSuiteCreateParameters)
            ->andThrow($serializedSuiteClientException)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $readinessAssessor = self::getContainer()->get(CreateSerializedSuiteReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        $handler = new CreateSerializedSuiteMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $serializedSuiteClient,
            $eventDispatcher,
            $authenticationTokenProvider,
            $notifyUrl,
        );

        $message = new CreateSerializedSuiteMessage(
            $job->getId(),
            $job->getSuiteId(),
            $serializedSuiteCreateParameters
        );

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
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $serializedSuiteParameters = [
            StringValue::random() => StringValue::random(),
        ];

        $serializedSuiteModel = new SerializedSuiteModel(
            $job->getId(),
            $job->getSuiteId(),
            $serializedSuiteParameters,
            'requested',
            new SourcesClientMetaState(false, false, true),
            null,
            null,
            [
                'preparing/running',
                'preparing/halted',
                'prepared',
                'failed',
            ],
            [],
        );

        $notifyUrl = self::getContainer()->getParameter('notify_url');
        \assert(is_string($notifyUrl));

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('create')
            ->with(self::$apiToken, $job->getId(), $job->getSuiteId(), $notifyUrl, $serializedSuiteParameters)
            ->andReturn($serializedSuiteModel)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $readinessAssessor = self::getContainer()->get(CreateSerializedSuiteReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        $handler = new CreateSerializedSuiteMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $serializedSuiteClient,
            $eventDispatcher,
            $authenticationTokenProvider,
            $notifyUrl
        );

        $handler(new CreateSerializedSuiteMessage(
            $job->getId(),
            $job->getSuiteId(),
            $serializedSuiteParameters
        ));

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteEntity = $serializedSuiteRepository->find($job->getId());
        self::assertInstanceOf(SerializedSuite::class, $serializedSuiteEntity);

        self::assertSame($serializedSuiteModel->getId(), $serializedSuiteEntity->id);

        $events = $this->eventRecorder->all(SerializedSuiteCreatedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new SerializedSuiteCreatedEvent($job->getId(), $serializedSuiteModel),
            $event
        );
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $suiteId = Id::generate();
        $serializedSuiteParameters = [
            StringValue::random() => StringValue::random(),
        ];
        $message = new CreateSerializedSuiteMessage($jobId, $suiteId, $serializedSuiteParameters);

        $serializedSuiteRepository = \Mockery::mock(SerializedSuiteRepository::class);
        $serializedSuiteRepository
            ->shouldReceive('has')
            ->with($jobId)
            ->andReturnTrue()
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        $notifyUrl = self::getContainer()->getParameter('notify_url');
        \assert(is_string($notifyUrl));

        $handler = new CreateSerializedSuiteMessageHandler(
            $assessor,
            $messageStateMutator,
            \Mockery::mock(SerializedSuiteClientInterface::class),
            $eventDispatcher,
            $authenticationTokenProvider,
            $notifyUrl,
        );

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
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
