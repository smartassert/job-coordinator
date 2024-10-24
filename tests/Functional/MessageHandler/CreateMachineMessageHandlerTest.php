<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Event\MachineCreationRequestedEvent;
use App\Exception\MachineCreationException;
use App\Message\CreateMachineMessage;
use App\MessageHandler\CreateMachineMessageHandler;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
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

class CreateMachineMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    /**
     * @param callable(Job): JobRepository                    $jobRepositoryCreator
     * @param callable(Job, ResultsJob): ResultsJobRepository $resultsJobRepositoryCreator
     * @param callable(Job): SerializedSuiteRepository        $serializedSuiteRepositoryCreator
     */
    #[DataProvider('invokeIncorrectStateDataProvider')]
    public function testInvokeIncorrectState(
        callable $jobRepositoryCreator,
        callable $resultsJobRepositoryCreator,
        callable $serializedSuiteRepositoryCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJob = $resultsJobFactory->createRandomForJob($job);

        $jobRepository = $jobRepositoryCreator($job);
        $resultsJobRepository = $resultsJobRepositoryCreator($job, $resultsJob);
        $serializedSuiteRepository = $serializedSuiteRepositoryCreator($job);

        $handler = $this->createHandler(
            $jobRepository,
            $resultsJobRepository,
            $serializedSuiteRepository,
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
                'serializedSuiteRepositoryCreator' => function () {
                    return \Mockery::mock(SerializedSuiteRepository::class);
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
                'serializedSuiteRepositoryCreator' => function () {
                    return \Mockery::mock(SerializedSuiteRepository::class);
                },
            ],
            'no serialized suite job' => [
                'jobRepositoryCreator' => function (Job $job) {
                    $jobRepository = \Mockery::mock(JobRepository::class);
                    $jobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturn($job)
                    ;

                    return $jobRepository;
                },
                'resultsJobRepositoryCreator' => function (Job $job, ResultsJob $resultsJob) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturn($resultsJob)
                    ;

                    return $resultsJobRepository;
                },
                'serializedSuiteRepositoryCreator' => function (Job $job) {
                    $serializedSuiteRepository = \Mockery::mock(SerializedSuiteRepository::class);
                    $serializedSuiteRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturnNull()
                    ;

                    return $serializedSuiteRepository;
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

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->createRandomForJob($job);

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuiteFactory->createPreparedForJob($job);

        $workerManagerException = new \Exception('Failed to create machine');

        $workerManagerClient = HttpMockedWorkerManagerClientFactory::create([$workerManagerException]);

        $handler = $this->createHandler(
            $jobRepository,
            $resultsJobRepository,
            $serializedSuiteRepository,
            $workerManagerClient
        );

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

        $serializedSuiteRepository = self::getContainer()->get(SerializedSuiteRepository::class);
        \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

        $serializedSuiteFactory = self::getContainer()->get(SerializedSuiteFactory::class);
        \assert($serializedSuiteFactory instanceof SerializedSuiteFactory);
        $serializedSuiteFactory->createPreparedForJob($job);

        $machine = MachineFactory::create(
            $job->id,
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
            $jobRepository,
            $resultsJobRepository,
            $serializedSuiteRepository,
            $workerManagerClient
        );

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
        SerializedSuiteRepository $serializedSuiteRepository,
        WorkerManagerClient $workerManagerClient,
    ): CreateMachineMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateMachineMessageHandler(
            $jobRepository,
            $resultsJobRepository,
            $serializedSuiteRepository,
            $workerManagerClient,
            $eventDispatcher
        );
    }
}
