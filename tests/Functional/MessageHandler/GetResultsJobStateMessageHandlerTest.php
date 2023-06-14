<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\ResultsJobStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Tests\Services\EventSubscriber\EventRecorder;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Component\Messenger\MessageBusInterface;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    private EventRecorder $eventRecorder;

    protected function setUp(): void
    {
        parent::setUp();

        $eventRecorder = self::getContainer()->get(EventRecorder::class);
        \assert($eventRecorder instanceof EventRecorder);
        $this->eventRecorder = $eventRecorder;
    }

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

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(jobId: $jobId);

        $resultsJobState = new ResultsJobState('complete', 'ended');

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('getJobStatus')
            ->with(self::$apiToken, $job->id)
            ->andReturn($resultsJobState)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        self::assertNull($job->getResultsToken());

        $handler(new GetResultsJobStateMessage(self::$apiToken, $jobId));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new ResultsJobStateRetrievedEvent(self::$apiToken, $jobId, $resultsJobState), $event);
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
