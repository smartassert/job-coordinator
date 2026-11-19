<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerJobRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\MessageDispatcher\GetWorkerJobMessageDispatcher;
use App\MessageDispatcher\JobRemoteRequestMessageDispatcher;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\WorkerClientJobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\Ip;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use webignition\SymfonyMessengerDelayMiddleware\NonDelayedStamp;

class GetWorkerJobMessageDispatcherTest extends WebTestCase
{
    private GetWorkerJobMessageDispatcher $dispatcher;
    private InMemoryTransport $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(GetWorkerJobMessageDispatcher::class);
        \assert($dispatcher instanceof GetWorkerJobMessageDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof InMemoryTransport);
        $this->messengerTransport = $messengerTransport;
    }

    #[DataProvider('eventSubscriptionsDataProvider')]
    public function testEventSubscriptions(string $expectedListenedForEvent, string $expectedMethod): void
    {
        $subscribedEvents = $this->dispatcher::getSubscribedEvents();
        self::assertArrayHasKey($expectedListenedForEvent, $subscribedEvents);

        $eventSubscriptions = $subscribedEvents[$expectedListenedForEvent];
        self::assertIsArray($eventSubscriptions[0]);

        $eventSubscription = $eventSubscriptions[0];
        self::assertSame($expectedMethod, $eventSubscription[0]);
    }

    /**
     * @return array<mixed>
     */
    public static function eventSubscriptionsDataProvider(): array
    {
        return [
            CreateWorkerJobRequestedEvent::class => [
                'expectedListenedForEvent' => CreateWorkerJobRequestedEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
            WorkerJobRetrievedEvent::class => [
                'expectedListenedForEvent' => WorkerJobRetrievedEvent::class,
                'expectedMethod' => 'dispatchImmediately',
            ],
        ];
    }

    public function testDispatchImmediatelyNotReady(): void
    {
        $jobId = Id::generate();
        $workerJob = WorkerClientJobFactory::createRandom();

        $event = new CreateWorkerJobRequestedEvent($jobId, $workerJob);

        $messageDispatcher = self::getContainer()->get(JobRemoteRequestMessageDispatcher::class);
        \assert($messageDispatcher instanceof JobRemoteRequestMessageDispatcher);

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $dispatcher = new GetWorkerJobMessageDispatcher($messageDispatcher, $assessor, $machineRepository);
        $dispatcher->dispatchImmediately($event);

        self::assertCount(0, $this->messengerTransport->getSent());
    }

    public function testDispatchImmediatelySuccess(): void
    {
        $jobId = Id::generate();
        $workerJob = WorkerClientJobFactory::createRandom();

        $machine = new Machine($jobId, 'up/active', 'active');
        $machine->setIpAddress(Ip::random());

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $machineRepository->save($machine);

        $event = new CreateWorkerJobRequestedEvent($jobId, $workerJob);

        $this->dispatcher->dispatchImmediately($event);

        $expectedMessage = new GetWorkerJobMessage($jobId);

        $envelopes = $this->messengerTransport->getSent();
        self::assertCount(1, $envelopes);

        $dispatchedEnvelope = $envelopes[0];
        self::assertEquals($expectedMessage, $dispatchedEnvelope->getMessage());

        self::assertEquals([new NonDelayedStamp()], $dispatchedEnvelope->all(NonDelayedStamp::class));
    }
}
