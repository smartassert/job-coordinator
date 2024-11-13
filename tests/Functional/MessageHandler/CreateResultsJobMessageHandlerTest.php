<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\MessageHandlerJobNotFoundException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\MessageHandler\CreateResultsJobMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJobModel;
use SmartAssert\ResultsClient\Model\JobState;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class CreateResultsJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeJobNotFound(): void
    {
        $handler = self::getContainer()->get(CreateResultsJobMessageHandler::class);
        \assert($handler instanceof CreateResultsJobMessageHandler);

        $jobId = (string) new Ulid();
        \assert('' !== $jobId);

        $message = new CreateResultsJobMessage('api token', $jobId);

        self::expectException(MessageHandlerJobNotFoundException::class);
        self::expectExceptionMessage('Failed to create results-job for job "' . $jobId . '": Job entity not found');

        $handler($message);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsClientException = new \Exception('Failed to create results job');

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler($jobRepository, $resultsClient, $resultsJobRepository);

        $message = new CreateResultsJobMessage(self::$apiToken, $job->getId());

        try {
            $handler($message);
            self::fail(RemoteJobActionException::class . ' not thrown');
        } catch (RemoteJobActionException $e) {
            self::assertSame($resultsClientException, $e->getPreviousException());
            self::assertSame([], $this->eventRecorder->all(ResultsJobCreatedEvent::class));
        }
    }

    public function testInvokeSuccess(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsJobModel = new ResultsJobModel(
            $job->getId(),
            md5((string) rand()),
            new JobState('awaiting-events', null)
        );

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $resultsJobModel->label,
                'token' => $resultsJobModel->token,
                'state' => $resultsJobModel->state->state,
                'end_state' => $resultsJobModel->state->endState,
            ])),
        ]);

        $handler = $this->createHandler($jobRepository, $resultsClient, $resultsJobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJob = $resultsJobRepository->find($job->getId());

        self::assertNull($resultsJob);

        $handler(new CreateResultsJobMessage(self::$apiToken, $job->getId()));

        $resultsJob = $resultsJobRepository->find($job->getId());
        self::assertEquals(
            new ResultsJobEntity(
                $resultsJobModel->label,
                $resultsJobModel->token,
                $resultsJobModel->state->state,
                $resultsJobModel->state->endState
            ),
            $resultsJob
        );

        $events = $this->eventRecorder->all(ResultsJobCreatedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new ResultsJobCreatedEvent(self::$apiToken, $job->getId(), $resultsJobModel), $event);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();
        \assert('' !== $job->getId());

        $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
        $resultsJobRepository
            ->shouldReceive('has')
            ->with($job->getId())
            ->andReturnTrue()
        ;

        $resultsClient = HttpMockedResultsClientFactory::create();

        $handler = $this->createHandler($jobRepository, $resultsClient, $resultsJobRepository);

        $message = new CreateResultsJobMessage(self::$apiToken, $job->getId());

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(ResultsJobCreatedEvent::class));

        $messageNotHandleableEvents = $this->eventRecorder->all(MessageNotHandleableEvent::class);
        self::assertCount(1, $messageNotHandleableEvents);

        $messageNotHandleableEvent = $messageNotHandleableEvents[0];
        self::assertInstanceOf(MessageNotHandleableEvent::class, $messageNotHandleableEvent);
        self::assertSame($message, $messageNotHandleableEvent->message);
    }

    protected function getHandlerClass(): string
    {
        return CreateResultsJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateResultsJobMessage::class;
    }

    private function createHandler(
        JobRepository $jobRepository,
        ResultsClient $resultsClient,
        ResultsJobRepository $resultsJobRepository,
    ): CreateResultsJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateResultsJobMessageHandler(
            $jobRepository,
            $resultsClient,
            $eventDispatcher,
            $resultsJobRepository
        );
    }
}
