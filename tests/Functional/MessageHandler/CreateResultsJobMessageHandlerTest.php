<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\MessageHandler\CreateResultsJobMessageHandler;
use App\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
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
    public function testInvokeResultsClientThrowsException(): void
    {
        $jobId = (string) new Ulid();

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $resultsClientException = new \Exception('Failed to create results job');

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler($resultsClient, $assessor);

        $message = new CreateResultsJobMessage(self::$apiToken, $jobId);

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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $assessor = self::getContainer()->get(CreateResultsJobReadinessAssessor::class);
        \assert($assessor instanceof CreateResultsJobReadinessAssessor);

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

        $handler = $this->createHandler($resultsClient, $assessor);

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
        $jobId = (string) new Ulid();

        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $resultsClient = HttpMockedResultsClientFactory::create();

        $handler = $this->createHandler($resultsClient, $assessor);

        $message = new CreateResultsJobMessage(self::$apiToken, $jobId);

        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(ResultsJobCreatedEvent::class));
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
        ResultsClient $resultsClient,
        ReadinessAssessorInterface $readinessAssessor,
    ): CreateResultsJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new CreateResultsJobMessageHandler($resultsClient, $eventDispatcher, $readinessAssessor);
    }
}
