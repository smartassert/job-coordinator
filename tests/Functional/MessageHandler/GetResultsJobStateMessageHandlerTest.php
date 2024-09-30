<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\ResultsJobStateRetrievalException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Component\Uid\Ulid;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNoJob(): void
    {
        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $handler = $this->createHandler(
            HttpMockedResultsClientFactory::create(),
            \Mockery::mock(JobPreparationInspectorInterface::class),
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsClientException = new \Exception('Failed to get results job status');

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $handler = $this->createHandler($resultsClient, $jobPreparationInspector);

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->id);

        try {
            $handler($message);
            self::fail(ResultsJobStateRetrievalException::class . ' not thrown');
        } catch (ResultsJobStateRetrievalException $e) {
            self::assertSame($resultsClientException, $e->getPreviousException());
            self::assertCount(0, $this->eventRecorder);
        }
    }

    public function testInvokeJobPreparationStateHasFailed(): void
    {
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsServiceJobState = new ResultsJobState(md5((string) rand()), md5((string) rand()));

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'state' => $resultsServiceJobState->state,
                'end_state' => $resultsServiceJobState->endState,
            ])),
        ]);

        $jobPreparationInspector = \Mockery::mock(JobPreparationInspectorInterface::class);
        $jobPreparationInspector
            ->shouldReceive('hasFailed')
            ->with($job)
            ->andReturnTrue()
        ;

        $handler = $this->createHandler($resultsClient, $jobPreparationInspector);

        $handler(new GetResultsJobStateMessage(self::$apiToken, $job->id));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        self::assertSame([], $events);
    }

    public function testInvokeSuccess(): void
    {
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsServiceJobState = new ResultsJobState(md5((string) rand()), md5((string) rand()));

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'state' => $resultsServiceJobState->state,
                'end_state' => $resultsServiceJobState->endState,
            ])),
        ]);

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $handler = $this->createHandler($resultsClient, $jobPreparationInspector);

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

    private function createHandler(
        ResultsClient $resultsClient,
        JobPreparationInspectorInterface $jobPreparationInspector,
    ): GetResultsJobStateMessageHandler {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetResultsJobStateMessageHandler(
            $jobRepository,
            $resultsClient,
            $eventDispatcher,
            $jobPreparationInspector
        );
    }
}
