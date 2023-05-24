<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Message\GetMachineMessage;
use App\MessageHandler\GetMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\Event;

class GetMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private EventRecorder $eventRecorder;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(GetMachineMessageHandler::class);
        self::assertInstanceOf(GetMachineMessageHandler::class, $handler);
        self::assertCount(1, (new \ReflectionClass($handler::class))->getAttributes(AsMessageHandler::class));
    }

    public function testInvokeNoJob(): void
    {
        $machine = new Machine(md5((string) rand()), 'find/received', 'finding', []);

        $this->createMessageAndHandleMessage($machine, $machine, self::$apiToken);

        $this->assertNoMessagesDispatched();
    }

    /**
     * @dataProvider invokeNoStateChangeDataProvider
     *
     * @param callable(Job): Machine $previousMachineCreator
     * @param callable(Job): Machine $currentMachineCreator
     */
    public function testInvokeNoStateChange(callable $previousMachineCreator, callable $currentMachineCreator): void
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        self::assertEquals(
            [new MachineRetrievedEvent(self::$apiToken, $previous, $current)],
            $this->eventRecorder->all(MachineRetrievedEvent::class)
        );

        $this->assertDispatchedMessage(self::$apiToken, $current);
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
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);

        $previous = $previousMachineCreator($job);
        $current = $currentMachineCreator($job);

        $this->createMessageAndHandleMessage($previous, $current, self::$apiToken);

        $expectedEvent = $expectedEventCreator($job, self::$apiToken);

        self::assertEquals([$expectedEvent], $this->eventRecorder->all($expectedEvent::class));
        $this->assertRemoteRequestEventsAreDispatched($previous, $current);
        $this->assertDispatchedMessage(self::$apiToken, $current);
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
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
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

        $envelopes = $this->messengerTransport->get();
        self::assertIsArray($envelopes);
        self::assertCount(0, $envelopes);
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
            ->with($authenticationToken, $previous->id)
            ->andReturn($current)
        ;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $handler = new GetMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
        $message = new GetMachineMessage($authenticationToken, $previous);

        ($handler)($message);
    }

    /**
     * @param non-empty-string $authenticationToken
     */
    private function assertDispatchedMessage(string $authenticationToken, Machine $current): void
    {
        $envelopes = $this->messengerTransport->get();

        $machineStateChangeCheckMessage = null;
        $machineStateChangeCheckMessageDelayStamps = [];
        $foundMessageClasses = [];

        foreach ($envelopes as $envelope) {
            if ($envelope instanceof Envelope) {
                $message = $envelope->getMessage();
                $foundMessageClasses[] = $message::class;

                if (GetMachineMessage::class === $message::class) {
                    $machineStateChangeCheckMessage = $message;
                    $machineStateChangeCheckMessageDelayStamps = $envelope->all(DelayStamp::class);
                }
            }
        }

        if (null === $machineStateChangeCheckMessage) {
            self::fail(sprintf(
                '%s message not dispatched, found: %s',
                GetMachineMessage::class,
                implode(', ', $foundMessageClasses)
            ));
        }

        self::assertEquals(
            new GetMachineMessage($authenticationToken, $current),
            $machineStateChangeCheckMessage
        );

        $messageDelays = self::getContainer()->getParameter('message_delays');
        \assert(is_array($messageDelays));

        $expectedDelayStampValue = $messageDelays[GetMachineMessage::class] ?? null;
        \assert(is_int($expectedDelayStampValue));

        self::assertEquals([new DelayStamp($expectedDelayStampValue)], $machineStateChangeCheckMessageDelayStamps);
    }
}
