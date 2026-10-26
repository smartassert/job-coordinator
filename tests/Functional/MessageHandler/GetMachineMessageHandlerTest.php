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
use App\Model\Machine\Machine;
use App\Model\MetaState;
use App\ReadinessAssessor\GetMachineReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MachineModelFactory;
use App\Services\MessageStateMutator;
use App\Services\WorkerManagerClientWrapper;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
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

        $machine = new MachineEntity(
            $job->getId(),
            StringValue::random(),
            StringValue::random(),
        );

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save($machine);

        $message = new GetMachineMessage($job->getId());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);
        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);

        $handler = $this->createHandler($wrapper, $assessor);

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
                $previous->getState(),
                $previous->getStateCategory(),
            )->setMetaState(
                new MetaState(
                    $previous->getMetaState()->ended,
                    $previous->getMetaState()->succeeded,
                    $previous->getMetaState()->pending,
                ),
            )
        );

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForMachine($current),
        ]);
        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);

        $handler = $this->createHandler($wrapper, $readinessAssessor);

        $handler(new GetMachineMessage($previous->getId()));

        self::assertEquals(
            [new MachineRetrievedEvent($current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );

        $events = $this->eventRecorder->all(MachineRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new MachineRetrievedEvent($current), $event);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeNoStateChangeDataProvider(): array
    {
        $machineCreator = function (JobInterface $job) {
            return new Machine(
                $job->getId(),
                'find/received',
                'finding',
                null,
                null,
                new MetaState(false, false, true),
                false,
                false,
                false,
            );
        };

        return [
            'find/received => find/received' => [
                'previousMachineCreator' => function (JobInterface $job) use ($machineCreator) {
                    return $machineCreator($job);
                },
                'currentMachineCreator' => function (JobInterface $job) use ($machineCreator) {
                    return $machineCreator($job);
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
                $previous->getState(),
                $previous->getStateCategory(),
            )->setMetaState(
                new MetaState(
                    $previous->getMetaState()->ended,
                    $previous->getMetaState()->succeeded,
                    $previous->getMetaState()->pending,
                ),
            )
        );

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForMachine($current),
        ]);
        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);

        $handler = $this->createHandler($wrapper, $readinessAssessor);

        $handler(new GetMachineMessage($previous->getId()));

        $expectedEvent = $expectedEventCreator($job, self::$apiToken);

        self::assertEquals([$expectedEvent], $this->eventRecorder->all($expectedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function invokeHasStateChangeDataProvider(): array
    {
        $machineCreator = function (JobInterface $job, string $state, string $stateCategory): Machine {
            return new Machine(
                $job->getId(),
                $state,
                $stateCategory,
                null,
                null,
                new MetaState(false, false, true),
                false,
                false,
                false,
            );
        };

        return [
            'unknown => find/received' => [
                'previousMachineCreator' => function (JobInterface $job) use ($machineCreator) {
                    return $machineCreator($job, 'unknown', 'unknown');
                },
                'currentMachineCreator' => function (JobInterface $job) use ($machineCreator) {
                    return $machineCreator($job, 'find/received', 'finding');
                },
                'expectedEventCreator' => function (
                    JobInterface $job,
                    string $authenticationToken,
                ) use (
                    $machineCreator,
                ) {
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent(
                        $machineCreator($job, 'find/received', 'finding'),
                    );
                },
            ],
            'unknown => active' => [
                'previousMachineCreator' => function (JobInterface $job) use ($machineCreator) {
                    return $machineCreator($job, 'unknown', 'unknown');
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return new Machine(
                        $job->getId(),
                        'up/active',
                        'active',
                        '127.0.0.1',
                        null,
                        new MetaState(false, false, false),
                        true,
                        false,
                        false,
                    );
                },
                'expectedEventCreator' => function (JobInterface $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent(
                        '127.0.0.1',
                        new Machine(
                            $job->getId(),
                            'up/active',
                            'active',
                            '127.0.0.1',
                            null,
                            new MetaState(false, false, false),
                            true,
                            false,
                            false,
                        ),
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

        $machineEntity = new MachineEntity(
            $job->getId(),
            $previous->getState(),
            $previous->getStateCategory(),
        );

        $machineEntity = $machineEntity->setMetaState(
            new MetaState(
                $previous->getMetaState()->ended,
                $previous->getMetaState()->succeeded,
                $previous->getMetaState()->pending,
            )
        );

        $previousIpAddress = $previous->getIpAddress();
        $previousIpAddress = '' === $previousIpAddress ? null : $previousIpAddress;
        if (null !== $previousIpAddress) {
            $machineEntity = $machineEntity->setIpAddress($previousIpAddress);
        }

        if ($previous->isActive()) {
            $machineEntity = $machineEntity->setIsActive();
        }

        $machineRepository->save($machineEntity);

        $readinessAssessor = self::getContainer()->get(GetMachineReadinessAssessor::class);
        \assert($readinessAssessor instanceof ReadinessAssessorInterface);

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            HttpResponseFactory::createForMachine($current),
        ]);
        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);

        $handler = $this->createHandler($wrapper, $readinessAssessor);

        $handler(new GetMachineMessage($previous->getId()));

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($current, $latestEvent->getMachine());

        self::assertEquals(
            [new MachineRetrievedEvent($current)],
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
                    return new Machine(
                        $job->getId(),
                        'up/active',
                        'active',
                        '127.0.0.1',
                        null,
                        new MetaState(false, false, false),
                        true,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (JobInterface $job) {
                    return new Machine(
                        $job->getId(),
                        'delete/deleted',
                        'end',
                        null,
                        null,
                        new MetaState(true, true, false),
                        false,
                        false,
                        false,
                    );
                },
                'expectedEventClass' => MachineStateChangeEvent::class,
            ],
        ];
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();

        $message = new GetMachineMessage($jobId);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create();
        $wrapper = $this->createWorkerManagerClientWrapper($workerManagerClient);

        $handler = $this->createHandler($wrapper, $assessor);

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
        WorkerManagerClientWrapper $wrapper,
        ReadinessAssessorInterface $readinessAssessor,
    ): GetMachineMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        return new GetMachineMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $wrapper,
            $eventDispatcher,
            $authenticationTokenProvider,
            $machineRepository,
        );
    }

    private function createWorkerManagerClientWrapper(WorkerManagerClient $client): WorkerManagerClientWrapper
    {
        $machineModelFactory = self::getContainer()->get(MachineModelFactory::class);
        \assert($machineModelFactory instanceof MachineModelFactory);

        return new WorkerManagerClientWrapper($client, $machineModelFactory);
    }
}
