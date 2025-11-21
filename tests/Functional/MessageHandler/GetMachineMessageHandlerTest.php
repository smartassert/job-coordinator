<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine as MachineEntity;
use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetMachineMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageHandler\GetMachineMessageHandler;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\FooReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\Event;

class GetMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        foreach ($remoteRequestRepository->findAll() as $remoteRequest) {
            $entityManager->remove($remoteRequest);
        }
        $entityManager->flush();
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobId = (string) new Ulid();
        $machine = MachineFactory::createRandomForJob($jobId);
        $message = new GetMachineMessage(self::$apiToken, $jobId, $machine);
        $assessor = $this->createAssessor($message, MessageHandlingReadiness::NOW);

        $workerManagerException = new \Exception('Failed to create machine');

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([$workerManagerException]),
            $assessor
        );

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(MachineRetrievedEvent::class));
        }
    }

    /**
     * @param callable(JobInterface): Machine $previousMachineCreator
     * @param callable(JobInterface): Machine $currentMachineCreator
     */
    #[DataProvider('invokeNoStateChangeDataProvider')]
    public function testInvokeNoStateChange(callable $previousMachineCreator, callable $currentMachineCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save(new MachineEntity(
            $job->getId(),
            $previous->state,
            $previous->stateCategory,
            $previous->hasFailedState,
            $previous->hasEndState,
        ));

        $readinessAssessor = self::getContainer()->get(FooReadinessAssessorInterface::class);
        \assert($readinessAssessor instanceof FooReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor
        );

        $handler(new GetMachineMessage(self::$apiToken, $previous->id, $previous));

        self::assertEquals(
            [new MachineRetrievedEvent(self::$apiToken, $previous, $current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );

        $events = $this->eventRecorder->all(MachineRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new MachineRetrievedEvent(self::$apiToken, $previous, $current), $event);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeNoStateChangeDataProvider(): array
    {
        return [
            'find/received => find/received' => [
                'previousMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'find/received',
                        'finding',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'find/received',
                        'finding',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
            ],
        ];
    }

    /**
     * @param callable(JobInterface): Machine       $previousMachineCreator
     * @param callable(JobInterface): Machine       $currentMachineCreator
     * @param callable(JobInterface, string): Event $expectedEventCreator
     */
    #[DataProvider('invokeHasStateChangeDataProvider')]
    public function testInvokeHasStateChange(
        callable $previousMachineCreator,
        callable $currentMachineCreator,
        callable $expectedEventCreator
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save(new MachineEntity(
            $job->getId(),
            $previous->state,
            $previous->stateCategory,
            $previous->hasFailedState,
            $previous->hasEndState,
        ));

        $readinessAssessor = self::getContainer()->get(FooReadinessAssessorInterface::class);
        \assert($readinessAssessor instanceof FooReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor,
        );

        $handler(new GetMachineMessage(self::$apiToken, $previous->id, $previous));

        $expectedEvent = $expectedEventCreator($job, self::$apiToken);

        self::assertEquals([$expectedEvent], $this->eventRecorder->all($expectedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function invokeHasStateChangeDataProvider(): array
    {
        return [
            'unknown => find/received' => [
                'previousMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'find/received',
                        'finding',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'expectedEventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent(
                        MachineFactory::create(
                            $job->getId(),
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            false,
                            false,
                        ),
                        MachineFactory::create(
                            $job->getId(),
                            'find/received',
                            'finding',
                            [],
                            false,
                            false,
                            false,
                            false,
                        )
                    );
                },
            ],
            'unknown => active' => [
                'previousMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'up/active',
                        'active',
                        ['127.0.0.1'],
                        false,
                        true,
                        false,
                        false,
                    );
                },
                'expectedEventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent(
                        $authenticationToken,
                        $job->getId(),
                        '127.0.0.1',
                        MachineFactory::create(
                            $job->getId(),
                            'up/active',
                            'active',
                            ['127.0.0.1'],
                            false,
                            true,
                            false,
                            false,
                        )
                    );
                },
            ],
        ];
    }

    /**
     * @param callable(JobInterface): Machine $previousMachineCreator
     * @param callable(JobInterface): Machine $currentMachineCreator
     * @param class-string                    $expectedEventClass
     */
    #[DataProvider('invokeHasEndStateChangeDataProvider')]
    public function testInvokeHasEndStateChange(
        callable $previousMachineCreator,
        callable $currentMachineCreator,
        string $expectedEventClass
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save(new MachineEntity(
            $job->getId(),
            $previous->state,
            $previous->stateCategory,
            $previous->hasFailedState,
            $previous->hasEndState,
        ));

        $readinessAssessor = self::getContainer()->get(FooReadinessAssessorInterface::class);
        \assert($readinessAssessor instanceof FooReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor,
        );

        $handler(new GetMachineMessage(self::$apiToken, $previous->id, $previous));

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->getMachine());

        self::assertEquals(
            [new MachineRetrievedEvent(self::$apiToken, $previous, $current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function invokeHasEndStateChangeDataProvider(): array
    {
        return [
            'up/active => delete/deleted' => [
                'previousMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'up/active',
                        'active',
                        [],
                        false,
                        true,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'delete/deleted',
                        'end',
                        [],
                        false,
                        true,
                        false,
                        true,
                    );
                },
                'expectedEventClass' => MachineStateChangeEvent::class,
            ],
        ];
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        $machine = MachineFactory::create(
            $jobId,
            'up/active',
            'active',
            [],
            false,
            true,
            false,
            false,
        );
        $message = new GetMachineMessage(self::$apiToken, $jobId, $machine);
        $assessor = $this->createAssessor($message, MessageHandlingReadiness::NEVER);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create(),
            readinessAssessor: $assessor,
        );

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(MachineRetrievedEvent::class));
    }

    protected function getHandlerClass(): string
    {
        return GetMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetMachineMessage::class;
    }

    private function createHandler(
        WorkerManagerClient $workerManagerClient,
        FooReadinessAssessorInterface $readinessAssessor,
    ): GetMachineMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetMachineMessageHandler($workerManagerClient, $eventDispatcher, $readinessAssessor);
    }

    private function createAssessor(
        JobRemoteRequestMessageInterface $message,
        MessageHandlingReadiness $readiness,
    ): FooReadinessAssessorInterface {
        $assessor = \Mockery::mock(FooReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->withArgs(function (RemoteRequestType $type, string $passedJobId) use ($message) {
                self::assertTrue($type->equals($message->getRemoteRequestType()));
                self::assertSame($passedJobId, $message->getJobId());

                return true;
            })
            ->andReturn($readiness)
        ;

        return $assessor;
    }
}
