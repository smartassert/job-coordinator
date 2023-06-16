<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\ResultsJobStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Component\Messenger\MessageBusInterface;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
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

        $handler = $this->createHandler(
            jobRepository: $jobRepository,
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

        $resultsClientException = new \Exception('Failed to get results job status');

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('getJobStatus')
            ->with(self::$apiToken, $job->id)
            ->andThrow($resultsClientException)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);

        try {
            $handler($message);
            self::fail(ResultsJobStateRetrievalException::class . ' not thrown');
        } catch (ResultsJobStateRetrievalException $e) {
            self::assertSame($resultsClientException, $e->getPreviousException());
            self::assertCount(0, $this->eventRecorder);
        }
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     *
     * @param callable(Job, ResultsJobRepository): ?ResultsJob $initialResultsJobCreator
     * @param callable(Job): ?ResultsJob                       $expectedResultsJobCreator
     */
    public function testInvokeSuccess(
        callable $initialResultsJobCreator,
        ResultsJobState $resultsServiceJobState,
        callable $expectedResultsJobCreator
    ): void {
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);
        $initialResultsJob = $initialResultsJobCreator($job, $resultsJobRepository);

        self::assertEquals($initialResultsJob, $resultsJobRepository->find($jobId));

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('getJobStatus')
            ->with(self::$apiToken, $job->id)
            ->andReturn($resultsServiceJobState)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        $handler(new GetResultsJobStateMessage(self::$apiToken, $jobId));

        $expectedResultsJob = $expectedResultsJobCreator($job);
        self::assertEquals($expectedResultsJob, $resultsJobRepository->find($jobId));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new ResultsJobStateRetrievedEvent(self::$apiToken, $jobId, $resultsServiceJobState), $event);
    }

    /**
     * @return array<mixed>
     */
    public function invokeSuccessDataProvider(): array
    {
        $token = md5((string) rand());

        return [
            'no initial resultsJob' => [
                'initialResultsJobCreator' => function () {
                    return null;
                },
                'resultsServiceJobState' => new ResultsJobState('complete', 'ended'),
                'expectedResultsJobCreator' => function () {
                    return null;
                },
            ],
            'has initial resultsJob' => [
                'initialResultsJobCreator' => function (
                    Job $job,
                    ResultsJobRepository $resultsJobRepository
                ) use (
                    $token
                ) {
                    $resultsJob = new ResultsJob($job->id, $token, 'initial state', 'initial end state');
                    $resultsJobRepository->save($resultsJob);

                    return $resultsJob;
                },
                'resultsServiceJobState' => new ResultsJobState('expected state', 'expected end state'),
                'expectedResultsJobCreator' => function (Job $job) use ($token) {
                    return new ResultsJob($job->id, $token, 'expected state', 'expected end state');
                },
            ],
        ];
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobStateMessage::class;
    }

    /**
     * @param non-empty-string $jobId
     */
    private function createJob(string $jobId): Job
    {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            600
        );

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?ResultsClient $resultsClient = null,
    ): GetResultsJobStateMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        if (null === $jobRepository) {
            $jobRepository = self::getContainer()->get(JobRepository::class);
            \assert($jobRepository instanceof JobRepository);
        }

        if (null === $resultsClient) {
            $resultsClient = \Mockery::mock(ResultsClient::class);
        }

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetResultsJobStateMessageHandler($jobRepository, $resultsClient, $eventDispatcher);
    }
}
