<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\ReadinessAssessor\CreateMachineReadinessAssessor;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Uid\Ulid;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotYetHandleable(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(CreateMachineReadinessAssessor::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($assessor, $workerManagerClient);
        $message = new CreateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));

        $messageNotYetHandleableEvents = $this->eventRecorder->all(MessageNotYetHandleableEvent::class);
        self::assertCount(1, $messageNotYetHandleableEvents);

        $messageNotYetHandleableEvent = $messageNotYetHandleableEvents[0];
        self::assertInstanceOf(MessageNotYetHandleableEvent::class, $messageNotYetHandleableEvent);
        self::assertSame($message, $messageNotYetHandleableEvent->message);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(CreateMachineReadinessAssessor::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $workerManagerClient = self::getContainer()->get(WorkerManagerClient::class);
        \assert($workerManagerClient instanceof WorkerManagerClient);

        $handler = $this->createHandler($assessor, $workerManagerClient);
        $message = new CreateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));

        $messageNotHandleableEvents = $this->eventRecorder->all(MessageNotHandleableEvent::class);
        self::assertCount(1, $messageNotHandleableEvents);

        $messageNotHandleableEvent = $messageNotHandleableEvents[0];
        self::assertInstanceOf(MessageNotHandleableEvent::class, $messageNotHandleableEvent);
        self::assertSame($message, $messageNotHandleableEvent->message);
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $assessor = \Mockery::mock(CreateMachineReadinessAssessor::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerManagerException = new \Exception('Failed to create machine');
        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($assessor, $workerManagerClient);
        $message = new CreateMachineMessage(self::$apiToken, $jobId);

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
        $job = $jobFactory->createRandom();

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteId = (string) new Ulid();
        \assert('' !== $serializedSuiteId);

        $serializedSuiteRepository->save(
            new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true)
        );

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsJobRepository->save(
            new ResultsJob($job->getId(), 'token', 'state', null)
        );

        $assessor = self::getContainer()->get(CreateMachineReadinessAssessor::class);
        \assert($assessor instanceof CreateMachineReadinessAssessor);

        $machine = MachineFactory::create(
            $job->getId(),
            'create/requested',
            'pre_active',
            [],
            false,
            false,
            false,
            false,
        );

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForWorkerManagerMachine($machine),
        ]);

        $handler = $this->createHandler($assessor, $workerManagerClient);
        $message = new CreateMachineMessage(self::$apiToken, $job->getId());

        $handler($message);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $createdMachine = $machineRepository->find($job->getId());
        self::assertEquals(
            new Machine($job->getId(), 'create/requested', 'pre_active', false, false),
            $createdMachine
        );

        $events = $this->eventRecorder->all(MachineCreationRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new MachineCreationRequestedEvent(self::$apiToken, $machine),
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
        CreateMachineReadinessAssessor $assessor,
        WorkerManagerClient $workerManagerClient,
    ): CreateMachineMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler($workerManagerClient, $eventDispatcher, $assessor);
    }
}
