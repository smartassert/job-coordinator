<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\ResultsJobCreatedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\CreateResultsJobMessage;
use App\MessageHandler\CreateResultsJobMessageHandler;
use App\Model\MetaState;
use App\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\ResultsJobRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJobModel;
use SmartAssert\ResultsClient\Model\JobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateResultsJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeResultsClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $message = new CreateResultsJobMessage($job->getId());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $resultsClientException = new \Exception('Failed to create results job');
        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler($resultsClient, $assessor);

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
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $assessor = self::getContainer()->get(CreateResultsJobReadinessAssessor::class);
        \assert($assessor instanceof CreateResultsJobReadinessAssessor);

        $resultsJobModel = new ResultsJobModel(
            $job->getId(),
            StringValue::random(),
            new JobState(
                'awaiting-events',
                null,
                new ResultsClientMetaState(false, false, true),
            ),
            false,
        );

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $resultsJobModel->label,
                'event_add_url' => $resultsJobModel->authenticator,
                'state' => $resultsJobModel->state->state,
                'end_state' => $resultsJobModel->state->endState,
                'has_events' => $resultsJobModel->hasEvents,
            ])),
        ]);

        $handler = $this->createHandler($resultsClient, $assessor);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $resultsJob = $resultsJobRepository->find($job->getId());

        self::assertNull($resultsJob);

        $handler(new CreateResultsJobMessage($job->getId()));

        $resultsJob = $resultsJobRepository->find($job->getId());
        self::assertEquals(
            new ResultsJobEntity(
                $resultsJobModel->label,
                $resultsJobModel->authenticator,
                $resultsJobModel->state->state,
                $resultsJobModel->state->endState,
                new MetaState(
                    $resultsJobModel->state->metaState->ended,
                    $resultsJobModel->state->metaState->succeeded,
                    $resultsJobModel->state->metaState->pending,
                ),
            ),
            $resultsJob
        );

        $events = $this->eventRecorder->all(ResultsJobCreatedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(new ResultsJobCreatedEvent(self::$apiToken, $job->getId(), $resultsJobModel), $event);
    }

    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();

        $message = new CreateResultsJobMessage($jobId);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $resultsClient = HttpMockedResultsClientFactory::create();
        $handler = $this->createHandler($resultsClient, $assessor);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
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

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new CreateResultsJobMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $resultsClient,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }
}
