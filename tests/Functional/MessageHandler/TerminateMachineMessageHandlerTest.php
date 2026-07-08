<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\MachineTerminationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\TerminateMachineMessage;
use App\MessageHandler\TerminateMachineMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use App\Tests\Services\Generator\Id;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\MetaState as WorkerManagerClientMetaState;
use Symfony\Component\Messenger\MessageBusInterface;

class TerminateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotYetHandleable(): void
    {
        $jobId = Id::generate();
        $message = new TerminateMachineMessage($jobId);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($workerManagerClient, $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::HALTED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $message = new TerminateMachineMessage($jobId);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($workerManagerClient, $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $message = new TerminateMachineMessage($job->getId());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerManagerException = new \Exception('Failed to terminate machine');
        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);
        $handler = $this->createHandler($workerManagerClient, $assessor);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            $this->assertSame([], $this->eventRecorder->all(MachineTerminationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $message = new TerminateMachineMessage($job->getId());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $machine = MachineFactory::create(
            $job->getId(),
            'create/requested',
            'pre_active',
            [],
            false,
            false,
            new WorkerManagerClientMetaState(false, false, true),
        );

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForWorkerManagerMachine($machine),
        ]);

        $handler = $this->createHandler($workerManagerClient, $assessor);

        $handler($message);

        $events = $this->eventRecorder->all(MachineTerminationRequestedEvent::class);
        $event = $events[0] ?? null;
        self::assertInstanceOf(MachineTerminationRequestedEvent::class, $event);

        self::assertSame($job->getId(), $event->getJobId());
    }

    protected function getHandlerClass(): string
    {
        return TerminateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return TerminateMachineMessage::class;
    }

    private function createHandler(
        WorkerManagerClient $workerManagerClient,
        ReadinessAssessorInterface $readinessAssessor,
    ): TerminateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new TerminateMachineMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $workerManagerClient,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }
}
