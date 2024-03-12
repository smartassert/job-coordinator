<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\ResultsJobStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Component\Uid\Ulid;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $handler = $this->createHandler();

        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $job = $this->createJob();

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

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->id);

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
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $job = $this->createJob();

        $resultsServiceJobState = new ResultsJobState(md5((string) rand()), md5((string) rand()));

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('getJobStatus')
            ->with(self::$apiToken, $job->id)
            ->andReturn($resultsServiceJobState)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        $handler(new GetResultsJobStateMessage(self::$apiToken, $job->id));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new ResultsJobStateRetrievedEvent(self::$apiToken, $job->id, $resultsServiceJobState),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobStateMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobStateMessage::class;
    }

    private function createJob(): Job
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), 600);

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?ResultsClient $resultsClient = null,
    ): GetResultsJobStateMessageHandler {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        if (null === $resultsClient) {
            $resultsClient = \Mockery::mock(ResultsClient::class);
        }

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetResultsJobStateMessageHandler($jobRepository, $resultsClient, $eventDispatcher);
    }
}
