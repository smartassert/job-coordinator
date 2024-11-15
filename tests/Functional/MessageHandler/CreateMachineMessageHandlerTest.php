<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Model\JobInterface;
use App\Model\SerializedSuite;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\SerializedSuiteStore;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Factory\SerializedSuiteFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    /**
     * @param callable(JobInterface): ResultsJobRepository $resultsJobRepositoryCreator
     * @param callable(JobInterface): SerializedSuiteStore $serializedSuiteStoreCreator
     */
    #[DataProvider('invokeNotYetHandleableDataProvider')]
    public function testInvokeNotYetHandleable(
        callable $resultsJobRepositoryCreator,
        callable $serializedSuiteStoreCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $resultsJobRepository = $resultsJobRepositoryCreator($job);
        $serializedSuiteStore = $serializedSuiteStoreCreator($job);

        $handler = $this->createHandler(
            $resultsJobRepository,
            $serializedSuiteStore,
            HttpMockedWorkerManagerClientFactory::create(),
            $machineRepository,
        );

        $message = new CreateMachineMessage(self::$apiToken, $job->getId());

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));

        $fooEvents = $this->eventRecorder->all(MessageNotYetHandleableEvent::class);
        self::assertCount(1, $fooEvents);

        $fooEvent = $fooEvents[0];
        self::assertInstanceOf(MessageNotYetHandleableEvent::class, $fooEvent);
        self::assertSame($message, $fooEvent->message);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeNotYetHandleableDataProvider(): array
    {
        return [
            'no results job' => [
                'resultsJobRepositoryCreator' => function (JobInterface $job) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('has')
                        ->with($job->getId())
                        ->andReturnFalse()
                    ;

                    return $resultsJobRepository;
                },
                'serializedSuiteStoreCreator' => function (JobInterface $job) {
                    $serializedSuiteStore = \Mockery::mock(SerializedSuiteStore::class);
                    $serializedSuiteStore
                        ->shouldReceive('retrieve')
                        ->with($job->getId())
                        ->andReturnNull()
                    ;

                    return $serializedSuiteStore;
                },
            ],
            'no serialized suite' => [
                'resultsJobRepositoryCreator' => function (JobInterface $job) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('has')
                        ->with($job->getId())
                        ->andReturnTrue()
                    ;

                    return $resultsJobRepository;
                },
                'serializedSuiteStoreCreator' => function (JobInterface $job) {
                    $serializedSuiteStore = \Mockery::mock(SerializedSuiteStore::class);
                    $serializedSuiteStore
                        ->shouldReceive('retrieve')
                        ->with($job->getId())
                        ->andReturnNull()
                    ;

                    return $serializedSuiteStore;
                },
            ],
            'serialized suite not prepared' => [
                'resultsJobRepositoryCreator' => function (JobInterface $job) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('has')
                        ->with($job->getId())
                        ->andReturnTrue()
                    ;

                    return $resultsJobRepository;
                },
                'serializedSuiteStoreCreator' => function (JobInterface $job) {
                    $serializedSuiteId = (string) new Ulid();
                    \assert('' !== $serializedSuiteId);

                    $serializedSuite = new SerializedSuite($serializedSuiteId, 'preparing', false, false);

                    $serializedSuiteStore = \Mockery::mock(SerializedSuiteStore::class);
                    $serializedSuiteStore
                        ->shouldReceive('retrieve')
                        ->with($job->getId())
                        ->andReturn($serializedSuite)
                    ;

                    return $serializedSuiteStore;
                },
            ],
        ];
    }

    public function testInvokeNotHandleable(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $machineRepository = \Mockery::mock(MachineRepository::class);
        $machineRepository
            ->shouldReceive('has')
            ->with($job->getId())
            ->andReturnTrue()
        ;

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $serializedSuiteStore = self::getContainer()->get(SerializedSuiteStore::class);
        \assert($serializedSuiteStore instanceof SerializedSuiteStore);

        $handler = $this->createHandler(
            $resultsJobRepository,
            $serializedSuiteStore,
            HttpMockedWorkerManagerClientFactory::create(),
            $machineRepository,
        );

        $message = new CreateMachineMessage(self::$apiToken, $job->getId());

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
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $serializedSuiteStore = self::getContainer()->get(SerializedSuiteStore::class);
        \assert($serializedSuiteStore instanceof SerializedSuiteStore);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuiteFactory->createPreparedForJob($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler(
            $resultsJobRepository,
            $serializedSuiteStore,
            $workerManagerClient,
            $machineRepository,
        );

        $message = new CreateMachineMessage(self::$apiToken, $job->getId());

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

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $serializedSuiteStore = self::getContainer()->get(SerializedSuiteStore::class);
        \assert($serializedSuiteStore instanceof SerializedSuiteStore);

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuiteFactory->createPreparedForJob($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

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

        $handler = $this->createHandler(
            $resultsJobRepository,
            $serializedSuiteStore,
            $workerManagerClient,
            $machineRepository,
        );

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        self::assertNull($machineRepository->find($job->getId()));

        $handler(new CreateMachineMessage(self::$apiToken, $job->getId()));

        $createdMachine = $machineRepository->find($job->getId());
        self::assertEquals(
            new Machine($job->getId(), 'create/requested', 'pre_active', false),
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
        ResultsJobRepository $resultsJobRepository,
        SerializedSuiteStore $serializedSuiteStore,
        WorkerManagerClient $workerManagerClient,
        MachineRepository $machineRepository,
    ): CreateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler(
            $resultsJobRepository,
            $serializedSuiteStore,
            $workerManagerClient,
            $eventDispatcher,
            $machineRepository,
        );
    }
}
