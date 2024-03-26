<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\ResultsJobCreationException;
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

class CreateResultsJobMessageHandlerTest extends AbstractMessageHandlerTestCase
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

        $handler = $this->createHandler($jobRepository, HttpMockedResultsClientFactory::create());

        $message = new CreateResultsJobMessage(self::$apiToken, $jobId);

        $handler($message);

        self::assertSame([], $this->eventRecorder->all(ResultsJobCreatedEvent::class));
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsClientException = new \Exception('Failed to create results job');

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler($jobRepository, $resultsClient);

        $message = new CreateResultsJobMessage(self::$apiToken, $job->id);

        try {
            $handler($message);
            self::fail(ResultsJobCreationException::class . ' not thrown');
        } catch (ResultsJobCreationException $e) {
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

        $resultsJobModel = new ResultsJobModel($job->id, md5((string) rand()), new JobState('awaiting-events', null));

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $resultsJobModel->label,
                'token' => $resultsJobModel->token,
                'state' => $resultsJobModel->state->state,
                'end_state' => $resultsJobModel->state->endState,
            ])),
        ]);

        $handler = $this->createHandler($jobRepository, $resultsClient);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJob = $resultsJobRepository->find($job->id);

        self::assertNull($resultsJob);

        $handler(new CreateResultsJobMessage(self::$apiToken, $job->id));

        $resultsJob = $resultsJobRepository->find($job->id);
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

        self::assertEquals(new ResultsJobCreatedEvent(self::$apiToken, $job->id, $resultsJobModel), $event);
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
    ): CreateResultsJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateResultsJobMessageHandler($jobRepository, $resultsClient, $eventDispatcher);
    }
}
