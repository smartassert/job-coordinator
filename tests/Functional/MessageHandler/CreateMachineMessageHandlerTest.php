<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = md5((string) rand());

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('find')
            ->with($jobId)
            ->andReturnNull()
        ;

        $handler = $this->createHandler($jobRepository, HttpMockedWorkerManagerClientFactory::create());

        $message = new CreateMachineMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($jobRepository, $workerManagerClient);

        $message = new CreateMachineMessage(self::$apiToken, $job->id);

        try {
            $handler($message);
            self::fail(MachineCreationException::class . ' not thrown');
        } catch (MachineCreationException $e) {
            self::assertSame($workerManagerException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $machine = MachineFactory::create($job->id, 'create/requested', 'pre_active', [], false);

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'id' => $machine->id,
                'state' => $machine->state,
                'state_category' => $machine->stateCategory,
                'ip_addresses' => $machine->ipAddresses,
                'has_failed_state' => $machine->hasFailedState,
            ])),
        ]);

        $handler = $this->createHandler($jobRepository, $workerManagerClient);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        self::assertNull($machineRepository->find($job->id));

        $handler(new CreateMachineMessage(self::$apiToken, $job->id));

        $createdMachine = $machineRepository->find($job->id);
        self::assertEquals(
            new Machine($job->id, 'create/requested', 'pre_active', false),
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
        JobRepository $jobRepository,
        WorkerManagerClient $workerManagerClient,
    ): CreateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler($jobRepository, $workerManagerClient, $eventDispatcher);
    }
}
