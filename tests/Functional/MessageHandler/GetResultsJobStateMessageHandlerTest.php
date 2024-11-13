<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob;
use App\Enum\RequestState;
use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\MessageHandlerTargetEntityNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Component\Uid\Ulid;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(GetResultsJobStateMessageHandler::class);
        \assert($handler instanceof GetResultsJobStateMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new GetResultsJobStateMessage('api token', $jobId);

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage('Failed to retrieve results-job for job "' . $jobId . '": Job entity not found');

        $handler($message);
    }

    public function testInvokeResultsJobNotFound(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $handler = self::getContainer()->get(GetResultsJobStateMessageHandler::class);
        \assert($handler instanceof GetResultsJobStateMessageHandler);

        $message = new GetResultsJobStateMessage('api token', $job->getId());

        try {
            $handler($message);
            self::fail(MessageHandlerTargetEntityNotFoundException::class . ' not thrown');
        } catch (MessageHandlerTargetEntityNotFoundException) {
        }

        self::expectNotToPerformAssertions();
    }

    public function testInvokeJobPreparationHasFailed(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $jobPreparationInspector = \Mockery::mock(JobPreparationInspectorInterface::class);
        $jobPreparationInspector
            ->shouldReceive('hasFailed')
            ->with($job)
            ->andReturnTrue()
        ;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $abortedResultsJobRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => 'results-job/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(0, $abortedResultsJobRetrieveRemoteRequests);

        $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
        $resultsJobRepository
            ->shouldReceive('find')
            ->with($job->getId())
            ->andReturn(new ResultsJob($job->getId(), 'token', 'state', 'end-state'))
        ;

        $handler = $this->createHandler(
            $jobPreparationInspector,
            $resultsJobRepository,
            HttpMockedResultsClientFactory::create(),
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->getId());

        $handler($message);

        self::assertEquals(
            [
                new MessageNotHandleableEvent($message),
            ],
            $this->eventRecorder->all(MessageNotHandleableEvent::class)
        );

        $abortedResultsJobRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => 'results-job/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(1, $abortedResultsJobRetrieveRemoteRequests);
    }

    public function testInvokeResultsJobHasEndState(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);

        $abortedResultsJobRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => 'results-job/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(0, $abortedResultsJobRetrieveRemoteRequests);

        $jobPreparationInspector = \Mockery::mock(JobPreparationInspectorInterface::class);
        $jobPreparationInspector
            ->shouldReceive('hasFailed')
            ->with($job)
            ->andReturnFalse()
        ;

        $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
        $resultsJobRepository
            ->shouldReceive('find')
            ->with($job->getId())
            ->andReturn(new ResultsJob($job->getId(), 'token', 'state', 'end-state'))
        ;

        $handler = $this->createHandler(
            $jobPreparationInspector,
            $resultsJobRepository,
            HttpMockedResultsClientFactory::create(),
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->getId());

        $handler($message);

        $abortedResultsJobRetrieveRemoteRequests = $remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => 'results-job/retrieve',
            'state' => RequestState::ABORTED,
        ]);

        self::assertCount(1, $abortedResultsJobRetrieveRemoteRequests);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->create($job);

        $resultsClientException = new \Exception('Failed to get results job status');

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler($jobPreparationInspector, $resultsJobRepository, $resultsClient);
        $message = new GetResultsJobStateMessage(self::$apiToken, $job->getId());

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($resultsClientException, $e->getPreviousException());
            self::assertCount(0, $this->eventRecorder);
        }
    }

    public function testInvokeSuccess(): void
    {
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobState = md5((string) rand());
        $resultsJobFactory->create($job, $resultsJobState);

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'state' => $resultsJobState,
                'end_state' => null,
            ])),
        ]);

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $handler = $this->createHandler($jobPreparationInspector, $resultsJobRepository, $resultsClient);

        $handler(new GetResultsJobStateMessage(self::$apiToken, $job->getId()));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new ResultsJobStateRetrievedEvent(
                self::$apiToken,
                $job->getId(),
                new ResultsJobState($resultsJobState, null)
            ),
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
        JobPreparationInspectorInterface $jobPreparationInspector,
        ResultsJobRepository $resultsJobRepository,
        ResultsClient $resultsClient,
    ): GetResultsJobStateMessageHandler {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetResultsJobStateMessageHandler(
            $jobRepository,
            $resultsJobRepository,
            $resultsClient,
            $eventDispatcher,
            $jobPreparationInspector
        );
    }
}
