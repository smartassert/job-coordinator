<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Model\Machine\Machine as MachineModel;
use App\Model\MetaState;
use App\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MachineModelFactory;
use App\Services\MessageStateMutator;
use App\Services\WorkerManagerClientWrapper;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Generator\Id;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotYetHandleable(): void
    {
        $jobId = Id::generate();
        $message = new CreateMachineMessage($jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);
        $handler = $this->createHandler($assessor, $wrapper);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::HALTED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $message = new CreateMachineMessage($jobId);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);
        $handler = $this->createHandler($assessor, $wrapper);

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

        $message = new CreateMachineMessage($job->getId());

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerManagerException = new \Exception('Failed to create machine');
        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $machineModelFactory = self::getContainer()->get(MachineModelFactory::class);
        \assert($machineModelFactory instanceof MachineModelFactory);

        $wrapper = new WorkerManagerClientWrapper($workerManagerClient, $machineModelFactory);

        $handler = $this->createHandler($assessor, $wrapper);

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteId = Id::generate();

        $serializedSuiteRepository->save(
            new SerializedSuite(
                $job->getId(),
                $serializedSuiteId,
                'prepared',
                new MetaState(true, true, false),
            )
        );

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $assessor = self::getContainer()->get(CreateMachineReadinessAssessor::class);
        \assert($assessor instanceof CreateMachineReadinessAssessor);

        $machine = new MachineModel(
            $job->getId(),
            'create/requested',
            'pre_active',
            null,
            null,
            new MetaState(false, false, true),
            false,
            false,
            false,
        );

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForMachine($machine),
        ]);

        $machineModelFactory = self::getContainer()->get(MachineModelFactory::class);
        \assert($machineModelFactory instanceof MachineModelFactory);

        $wrapper = new WorkerManagerClientWrapper($workerManagerClient, $machineModelFactory);

        $handler = $this->createHandler($assessor, $wrapper);
        $message = new CreateMachineMessage($job->getId());

        $handler($message);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $createdMachine = $machineRepository->find($job->getId());
        self::assertEquals(
            new Machine($job->getId(), 'create/requested', 'pre_active'),
            $createdMachine
        );

        $events = $this->eventRecorder->all(MachineCreationRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new MachineCreationRequestedEvent($machine),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return CreateMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateMachineMessage::class;
    }

    private function createHandler(
        ReadinessAssessorInterface $assessor,
        WorkerManagerClientWrapper $wrapper,
    ): CreateMachineMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new CreateMachineMessageHandler(
            $assessor,
            $messageStateMutator,
            $wrapper,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }

    private function createWorkerManagerClientWrapper(WorkerManagerClient $client): WorkerManagerClientWrapper
    {
        $machineModelFactory = self::getContainer()->get(MachineModelFactory::class);
        \assert($machineModelFactory instanceof MachineModelFactory);

        return new WorkerManagerClientWrapper($client, $machineModelFactory);
    }
}
