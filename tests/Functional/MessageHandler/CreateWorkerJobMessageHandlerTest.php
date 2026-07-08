<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\SerializedSuite;
use App\Entity\WorkerJobCreationFailure;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Enum\WorkerJobCreationStage;
use App\Event\CreateWorkerJobFailedEvent;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateWorkerJobMessage;
use App\MessageHandler\CreateWorkerJobMessageHandler;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\CreateWorkerJobReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerJobCreationFailureRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Services\UnhandleableMessageHandler;
use App\Services\WorkerClientFactory;
use App\Tests\Services\Factory\HttpMockedWorkerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\Ip;
use App\Tests\Services\Generator\StringValue;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use SmartAssert\ServiceClient\Exception\CurlException;
use SmartAssert\SourcesClient\SerializedSuiteClientInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class CreateWorkerJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    public function testInvokeNotYetHandleable(): void
    {
        $jobId = Id::generate();
        $message = new CreateWorkerJobMessage($jobId, 600, Ip::random());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::EVENTUALLY)
        ;

        $handler = $this->createHandler(readinessAssessor: $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::HALTED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $message = new CreateWorkerJobMessage($jobId, 600, Ip::random());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $handler = $this->createHandler(readinessAssessor: $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeReadSerializedSuiteThrowsException(): void
    {
        $workerJobCreationFailureRepository = self::getContainer()->get(WorkerJobCreationFailureRepository::class);
        \assert($workerJobCreationFailureRepository instanceof WorkerJobCreationFailureRepository);

        $job = $this->createJob();
        $jobId = $job->getId();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machine = new Machine($jobId, 'up/active', 'active');
        $machine->setIsActive();
        $machineRepository->save($machine);

        self::assertNull($workerJobCreationFailureRepository->find($jobId));

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', new MetaState(true, true, false));
        $this->createResultsJob($job);

        $serializedSuiteReadException = new \Exception('Failed to read serialized suite');

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andThrow($serializedSuiteReadException)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
        );

        $message = new CreateWorkerJobMessage(
            $jobId,
            $job->getMaximumDurationInSeconds(),
            Ip::random()
        );

        $exception = null;

        try {
            $handler($message);
        } catch (RemoteJobActionException $exception) {
        }

        if (null === $exception) {
            self::fail(RemoteJobActionException::class . ' not thrown');
        }

        self::assertSame($serializedSuiteReadException, $exception->getPreviousException());
        self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
        $this->assertNoStartWorkerJobMessageDispatched();

        $expectedEvent = new CreateWorkerJobFailedEvent(
            $jobId,
            WorkerJobCreationStage::SERIALIZED_SUITE_READ,
            $serializedSuiteReadException
        );
        self::assertEquals([$expectedEvent], $this->eventRecorder->all(CreateWorkerJobFailedEvent::class));

        $expectedEntity = new WorkerJobCreationFailure(
            $jobId,
            WorkerJobCreationStage::SERIALIZED_SUITE_READ,
            $serializedSuiteReadException
        );

        self::assertEquals(
            $expectedEntity,
            $workerJobCreationFailureRepository->find($jobId)
        );
    }

    public function testInvokeCreateWorkerJobThrowsException(): void
    {
        $workerJobCreationFailureRepository = self::getContainer()->get(WorkerJobCreationFailureRepository::class);
        \assert($workerJobCreationFailureRepository instanceof WorkerJobCreationFailureRepository);

        $job = $this->createJob();
        $jobId = $job->getId();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machine = new Machine($jobId, 'up/active', 'active');
        $machine->setIsActive();
        $machineRepository->save($machine);

        self::assertNull($workerJobCreationFailureRepository->find($jobId));

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', new MetaState(true, true, false));
        $this->createResultsJob($job);

        $serializedSuiteContent = StringValue::random();

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andReturn($serializedSuiteContent)
        ;

        $machineIpAddress = Ip::random();

        $workerJobCreateException = new CurlException(
            \Mockery::mock(RequestInterface::class),
            7,
            sprintf(
                'Failed to connect to %s port 80 after 190 ms: '
                . 'Could not connect to server (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
                . 'for http://%s/job',
                $machineIpAddress,
                $machineIpAddress,
            )
        );

        $workerClient = HttpMockedWorkerClientFactory::create([$workerJobCreateException]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
            workerClientFactory: $workerClientFactory,
        );

        $message = new CreateWorkerJobMessage(
            $jobId,
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

        $exception = null;

        try {
            $handler($message);
        } catch (RemoteJobActionException $exception) {
        }

        if (null === $exception) {
            self::fail(RemoteJobActionException::class . ' not thrown');
        }

        self::assertSame($workerJobCreateException, $exception->getPreviousException());
        self::assertEquals([], $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class));
        $this->assertNoStartWorkerJobMessageDispatched();

        $expectedEvent = new CreateWorkerJobFailedEvent(
            $jobId,
            WorkerJobCreationStage::WORKER_JOB_CREATE,
            $workerJobCreateException
        );
        self::assertEquals([$expectedEvent], $this->eventRecorder->all(CreateWorkerJobFailedEvent::class));

        $expectedEntity = new WorkerJobCreationFailure(
            $jobId,
            WorkerJobCreationStage::WORKER_JOB_CREATE,
            $workerJobCreateException
        );

        self::assertEquals(
            $expectedEntity,
            $workerJobCreationFailureRepository->find($jobId)
        );
    }

    public function testInvokeSuccess(): void
    {
        $job = $this->createJob();
        $jobId = $job->getId();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machine = new Machine($jobId, 'up/active', 'active');
        $machine->setIsActive();
        $machineRepository->save($machine);

        $serializedSuite = $this->createSerializedSuite($job, 'prepared', new MetaState(true, true, false));
        $this->createResultsJob($job);

        $serializedSuiteContent = StringValue::random();

        $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        $serializedSuiteClient
            ->shouldReceive('read')
            ->with(self::$apiToken, $serializedSuite->id)
            ->andReturn($serializedSuiteContent)
        ;

        $machineIpAddress = Ip::random();
        $workerJob = WorkerClientJobFactory::createRandom();

        $workerClient = HttpMockedWorkerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $workerJob->reference->label,
                'reference' => $workerJob->reference->reference,
                'maximum_duration_in_seconds' => $workerJob->maximumDurationInSeconds,
                'sources' => [],
                'test_paths' => [],
                'references' => [],
                'tests' => [],
            ])),
        ]);

        $workerClientFactory = \Mockery::mock(WorkerClientFactory::class);
        $workerClientFactory
            ->shouldReceive('create')
            ->with($machineIpAddress)
            ->andReturn($workerClient)
        ;

        $handler = $this->createHandler(
            serializedSuiteClient: $serializedSuiteClient,
            workerClientFactory: $workerClientFactory,
        );

        $message = new CreateWorkerJobMessage(
            $jobId,
            $job->getMaximumDurationInSeconds(),
            $machineIpAddress
        );

        $handler($message);

        $events = $this->eventRecorder->all(CreateWorkerJobRequestedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new CreateWorkerJobRequestedEvent($jobId, $machineIpAddress, $workerJob),
            $event
        );

        $this->assertNoStartWorkerJobMessageDispatched();
    }

    protected function getHandlerClass(): string
    {
        return CreateWorkerJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateWorkerJobMessage::class;
    }

    private function createJob(): JobInterface
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        return $jobFactory->createForUserToken(self::$apiToken);
    }

    private function createResultsJob(JobInterface $job): void
    {
        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);
    }

    /**
     * @param non-empty-string $state
     */
    private function createSerializedSuite(
        JobInterface $job,
        string $state,
        MetaState $metaState,
    ): SerializedSuite {
        $serializedSuite = new SerializedSuite(
            $job->getId(),
            Id::generate(),
            $state,
            $metaState,
        );

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);
        $serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }

    private function createHandler(
        ?SerializedSuiteClientInterface $serializedSuiteClient = null,
        ?WorkerClientFactory $workerClientFactory = null,
        ?ReadinessAssessorInterface $readinessAssessor = null,
    ): CreateWorkerJobMessageHandler {
        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        if (null === $serializedSuiteClient) {
            $serializedSuiteClient = \Mockery::mock(SerializedSuiteClientInterface::class);
        }

        if (null === $workerClientFactory) {
            $workerClientFactory = self::getContainer()->get(WorkerClientFactory::class);
            \assert($workerClientFactory instanceof WorkerClientFactory);
        }

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        if (null === $readinessAssessor) {
            $readinessAssessor = self::getContainer()->get(CreateWorkerJobReadinessAssessor::class);
            \assert($readinessAssessor instanceof ReadinessAssessorInterface);
        }

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $unhandleableMessageHandler = self::getContainer()->get(UnhandleableMessageHandler::class);
        \assert($unhandleableMessageHandler instanceof UnhandleableMessageHandler);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new CreateWorkerJobMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $unhandleableMessageHandler,
            $serializedSuiteRepository,
            $resultsJobRepository,
            $serializedSuiteClient,
            $workerClientFactory,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }

    private function assertNoStartWorkerJobMessageDispatched(): void
    {
        foreach ($this->messengerTransport->getSent() as $envelope) {
            self::assertFalse($envelope->getMessage() instanceof CreateWorkerJobMessage);
        }
    }
}
