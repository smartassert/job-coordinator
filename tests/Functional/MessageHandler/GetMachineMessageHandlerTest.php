<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine as MachineEntity;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use App\Tests\Services\Generator\Id;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use SmartAssert\WorkerManagerClient\Model\MetaState as WorkerManagerClientMetaState;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::createRandomForJob($job->getId());
        $message = new GetMachineMessage($job->getId(), $machine);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

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
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machineRepository->save(
            new MachineEntity(
                $job->getId(),
                $previous->state,
                $previous->stateCategory,
            )->setMetaState(
                new MetaState(
                    $previous->metaState->ended,
                    $previous->metaState->succeeded,
                    $previous->metaState->pending,
                ),
            )
        );

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor
        );

        $handler(new GetMachineMessage($previous->id, $previous));

        self::assertEquals(
            [new MachineRetrievedEvent($previous, $current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );

        $events = $this->eventRecorder->all(MachineRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new MachineRetrievedEvent($previous, $current), $event);
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
                        new WorkerManagerClientMetaState(false, false, true),
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
                        new WorkerManagerClientMetaState(false, false, true),
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
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machineRepository->save(
            new MachineEntity(
                $job->getId(),
                $previous->state,
                $previous->stateCategory,
            )->setMetaState(
                new MetaState(
                    $previous->metaState->ended,
                    $previous->metaState->succeeded,
                    $previous->metaState->pending,
                ),
            )
        );

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor,
        );

        $handler(new GetMachineMessage($previous->id, $previous));

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
                        new WorkerManagerClientMetaState(false, false, true),
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
                        new WorkerManagerClientMetaState(false, false, true),
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
                            new WorkerManagerClientMetaState(false, false, true),
                        ),
                        MachineFactory::create(
                            $job->getId(),
                            'find/received',
                            'finding',
                            [],
                            false,
                            false,
                            new WorkerManagerClientMetaState(false, false, true),
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
                        new WorkerManagerClientMetaState(false, false, true),
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'up/active',
                        'active',
                        ['127.0.0.1'],
                        true,
                        false,
                        new WorkerManagerClientMetaState(false, false, false),
                    );
                },
                'expectedEventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent(
                        $job->getId(),
                        '127.0.0.1',
                        MachineFactory::create(
                            $job->getId(),
                            'up/active',
                            'active',
                            ['127.0.0.1'],
                            true,
                            false,
                            new WorkerManagerClientMetaState(false, false, false),
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
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machineRepository->save(
            new MachineEntity(
                $job->getId(),
                $previous->state,
                $previous->stateCategory,
            )->setMetaState(
                new MetaState(
                    $previous->metaState->ended,
                    $previous->metaState->succeeded,
                    $previous->metaState->pending,
                ),
            )
        );

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create([
                HttpResponseFactory::createForWorkerManagerMachine($current),
            ]),
            $readinessAssessor,
        );

        $handler(new GetMachineMessage($previous->id, $previous));

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->getMachine());

        self::assertEquals(
            [new MachineRetrievedEvent($previous, $current)],
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
                        true,
                        false,
                        new WorkerManagerClientMetaState(false, false, false),
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return MachineFactory::create(
                        $job->getId(),
                        'delete/deleted',
                        'end',
                        [],
                        false,
                        false,
                        new WorkerManagerClientMetaState(true, true, false),
                    );
                },
                'expectedEventClass' => MachineStateChangeEvent::class,
            ],
        ];
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $machine = MachineFactory::create(
            $jobId,
            'up/active',
            'active',
            [],
            true,
            false,
            new WorkerManagerClientMetaState(false, false, false),
        );
        $message = new GetMachineMessage($jobId, $machine);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $handler = $this->createHandler(
            HttpMockedWorkerManagerClientFactory::create(),
            readinessAssessor: $assessor,
        );

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
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
        ReadinessAssessorInterface $readinessAssessor,
    ): GetMachineMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new GetMachineMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $workerManagerClient,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }
}
