<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use SmartAssert\WorkerManagerClient\ClientInterface as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\Event;

class GetMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;

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

        $machine = new Machine($jobId, 'find/received', 'finding', []);

        $this->createMessageAndHandleMessage($machine, $machine, self::$apiToken);

        self::assertSame([], $this->eventRecorder->all(MachineRetrievedEvent::class));
    }

    /**
     * @dataProvider invokeNoStateChangeDataProvider
     *
     * @param callable(Job): Machine $previousMachineCreator
     * @param callable(Job): Machine $currentMachineCreator
     */
    public function testInvokeNoStateChange(callable $previousMachineCreator, callable $currentMachineCreator): void
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

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
    public function invokeNoStateChangeDataProvider(): array
    {
        return [
            'find/received => find/received' => [
                'previousMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'find/received', 'finding', []);
                },
                'currentMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'find/received', 'finding', []);
                },
            ],
        ];
    }

    /**
     * @dataProvider invokeHasStateChangeDataProvider
     *
     * @param callable(Job): Machine       $previousMachineCreator
     * @param callable(Job): Machine       $currentMachineCreator
     * @param callable(Job, string): Event $expectedEventCreator
     */
    public function testInvokeHasStateChange(
        callable $previousMachineCreator,
        callable $currentMachineCreator,
        callable $expectedEventCreator
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        $expectedEvent = $expectedEventCreator($job, self::$apiToken);

        self::assertEquals([$expectedEvent], $this->eventRecorder->all($expectedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public function invokeHasStateChangeDataProvider(): array
    {
        return [
            'unknown => find/received' => [
                'previousMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'unknown', 'unknown', []);
                },
                'currentMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'find/received', 'finding', []);
                },
                'expectedEventCreator' => function (Job $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineStateChangeEvent(
                        $authenticationToken,
                        new Machine($job->id, 'unknown', 'unknown', []),
                        new Machine($job->id, 'find/received', 'finding', [])
                    );
                },
            ],
            'unknown => active' => [
                'previousMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'unknown', 'unknown', []);
                },
                'currentMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'up/active', 'active', ['127.0.0.1']);
                },
                'expectedEventCreator' => function (Job $job, string $authenticationToken) {
                    \assert('' !== $authenticationToken);

                    return new MachineIsActiveEvent($authenticationToken, $job->id, '127.0.0.1');
                },
            ],
        ];
    }

    /**
     * @dataProvider invokeHasEndStateChangeDataProvider
     *
     * @param callable(Job): Machine $previousMachineCreator
     * @param callable(Job): Machine $currentMachineCreator
     * @param class-string           $expectedEventClass
     */
    public function testInvokeHasEndStateChange(
        callable $previousMachineCreator,
        callable $currentMachineCreator,
        string $expectedEventClass
    ): void {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        $latestEvent = $this->eventRecorder->getLatest();
        self::assertNotNull($latestEvent);
        self::assertInstanceOf($expectedEventClass, $latestEvent);
        self::assertInstanceOf(MachineStateChangeEvent::class, $latestEvent);
        self::assertEquals($previous, $latestEvent->previous);
        self::assertEquals($current, $latestEvent->current);
        self::assertEquals(self::$apiToken, $latestEvent->authenticationToken);

        self::assertEquals(
            [new MachineRetrievedEvent(self::$apiToken, $previous, $current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );
    }

    /**
     * @return array<mixed>
     */
    public function invokeHasEndStateChangeDataProvider(): array
    {
        return [
            'up/active => delete/deleted' => [
                'previousMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'up/active', 'active', []);
                },
                'currentMachineCreator' => function (Job $job) {
                    return new Machine($job->id, 'delete/deleted', 'end', []);
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
     */
    private function createMessageAndHandleMessage(
        Machine $previous,
        Machine $current,
        string $authenticationToken,
    ): void {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $workerManagerClient = \Mockery::mock(WorkerManagerClient::class);
        $workerManagerClient
            ->shouldReceive('getMachine')
            ->with($authenticationToken, $previous->getId())
            ->andReturn($current)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
        $message = new GetMachineMessage($authenticationToken, $previous->getId(), $previous);

        ($handler)($message);
    }
}
