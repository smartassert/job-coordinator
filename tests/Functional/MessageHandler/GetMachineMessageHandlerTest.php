<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Exception\MachineRetrievalException;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\HttpResponseFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(GetMachineMessageHandler::class);
        self::assertInstanceOf(GetMachineMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $machine = MachineFactory::create(
            $jobId,
            'find/received',
            'finding',
            [],
            false,
            false,
            false,
            false,
        );

        $this->createMessageAndHandleMessage($machine, self::$apiToken, null);

        self::assertSame([], $this->eventRecorder->all(MachineRetrievedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::createRandomForJob($job->id);

        $workerManagerException = new \Exception('Failed to create machine');

        try {
            $this->createMessageAndHandleMessage($machine, self::$apiToken, $workerManagerException);
            self::fail(MachineRetrievalException::class . ' not thrown');
        } catch (MachineRetrievalException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(MachineRetrievalException::class));
        }
    }

    /**
     * @param callable(Job): Machine $previousMachineCreator
     * @param callable(Job): Machine $currentMachineCreator
     */
    #[DataProvider('invokeNoStateChangeDataProvider')]
    public function testInvokeNoStateChange(callable $previousMachineCreator, callable $currentMachineCreator): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage(
            $previous,
            self::$apiToken,
            HttpResponseFactory::createForWorkerManagerMachine($current),
        );

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
                'previousMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'find/received',
                        'finding',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
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
     * @param callable(Job): Machine       $previousMachineCreator
     * @param callable(Job): Machine       $currentMachineCreator
     * @param callable(Job, string): Event $expectedEventCreator
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

        $this->createMessageAndHandleMessage(
            $previous,
            self::$apiToken,
            HttpResponseFactory::createForWorkerManagerMachine($current)
        );

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
                'previousMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'find/received',
                        'finding',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'expectedEventCreator' => function (Job $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent(
                        MachineFactory::create(
                            $job->id,
                            'unknown',
                            'unknown',
                            [],
                            false,
                            false,
                            false,
                            false,
                        ),
                        MachineFactory::create(
                            $job->id,
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
                'previousMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'unknown',
                        'unknown',
                        [],
                        false,
                        false,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'up/active',
                        'active',
                        ['127.0.0.1'],
                        false,
                        true,
                        false,
                        false,
                    );
                },
                'expectedEventCreator' => function (Job $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent($authenticationToken, $job->id, '127.0.0.1');
                },
            ],
        ];
    }

    /**
     * @param callable(Job): Machine $previousMachineCreator
     * @param callable(Job): Machine $currentMachineCreator
     * @param class-string           $expectedEventClass
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

        $this->createMessageAndHandleMessage(
            $previous,
            self::$apiToken,
            HttpResponseFactory::createForWorkerManagerMachine($current)
        );

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->current);

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
                'previousMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
                        'up/active',
                        'active',
                        [],
                        false,
                        true,
                        false,
                        false,
                    );
                },
                'currentMachineCreator' => function (Job $job) {
                    return MachineFactory::create(
                        $job->id,
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

    protected function getHandlerClass(): string
    {
        return GetMachineMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetMachineMessage::class;
    }

    /**
     * @param non-empty-string $authenticationToken
     *
     * @throws \Exception|MachineRetrievalException
     */
    private function createMessageAndHandleMessage(
        Machine $previous,
        string $authenticationToken,
        null|ResponseInterface|\Throwable $httpFixture,
    ): void {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $httpFixtures = null === $httpFixture ? [] : [$httpFixture];

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create($httpFixtures);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
        $message = new GetMachineMessage($authenticationToken, $previous->id, $previous);

        ($handler)($message);
    }
}
