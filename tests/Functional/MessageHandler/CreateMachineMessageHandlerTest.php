<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\HttpMockedWorkerManagerClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\WorkerManagerClient\Client as WorkerManagerClient;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    /**
     * @param callable(Job): JobRepository        $jobRepositoryCreator
     * @param callable(Job): ResultsJobRepository $resultsJobRepositoryCreator
     */
    #[DataProvider('invokeIncorrectStateDataProvider')]
    public function testInvokeIncorrectState(
        callable $jobRepositoryCreator,
        callable $resultsJobRepositoryCreator
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);

        $job = $jobFactory->createRandom();

        $jobRepository = $jobRepositoryCreator($job);
        $resultsJobRepository = $resultsJobRepositoryCreator($job);

        $handler = $this->createHandler(
            $jobRepository,
            $resultsJobRepository,
            HttpMockedWorkerManagerClientFactory::create()
        );

        $message = new CreateMachineMessage(self::$apiToken, $job->id);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(MachineCreationRequestedEvent::class));
    }

    /**
     * @return array<mixed>
     */
    public static function invokeIncorrectStateDataProvider(): array
    {
        return [
            'no job' => [
                'jobRepositoryCreator' => function (Job $job) {
                    $jobRepository = \Mockery::mock(JobRepository::class);
                    $jobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturnNull()
                    ;

                    return $jobRepository;
                },
                'resultsJobRepositoryCreator' => function () {
                    return \Mockery::mock(ResultsJobRepository::class);
                },
            ],
            'no results job' => [
                'jobRepositoryCreator' => function (Job $job) {
                    $jobRepository = \Mockery::mock(JobRepository::class);
                    $jobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturn($job)
                    ;

                    return $jobRepository;
                },
                'resultsJobRepositoryCreator' => function (Job $job) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturnNull()
                    ;

                    return $resultsJobRepository;
                },
            ],
        ];
    }

    public function testInvokeWorkerManagerClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->createRandomForJob($job);

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler($jobRepository, $resultsJobRepository, $workerManagerClient);

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

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->createRandomForJob($job);

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

        $handler = $this->createHandler($jobRepository, $resultsJobRepository, $workerManagerClient);

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
        ResultsJobRepository $resultsJobRepository,
        WorkerManagerClient $workerManagerClient,
    ): CreateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler(
            $jobRepository,
            $resultsJobRepository,
            $workerManagerClient,
            $eventDispatcher
        );
    }
}
