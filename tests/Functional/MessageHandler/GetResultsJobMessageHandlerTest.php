<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Enum\MessageState;
use App\Event\ResultsJobRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobMessage;
use App\MessageHandler\GetResultsJobMessageHandler;
use App\ReadinessAssessor\GetResultsJobReadinessAssessor;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Services\AuthenticationTokenProvider;
use App\Services\MessageStateMutator;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;

class GetResultsJobMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = Id::generate();
        $message = new GetResultsJobMessage($jobId);
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($jobId)
            ->andReturn(MessageHandlingReadiness::NEVER)
        ;

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $handler = $this->createHandler($assessor, $resultsClient);

        self::assertSame(MessageState::HANDLING, $message->getState());

        $handler($message);

        self::assertSame(MessageState::STOPPED, $message->getState());
        $this->assertMessageNotHandleableMessageIsDispatched($message);
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $message = new GetResultsJobMessage($job->getId());
        $assessor = \Mockery::mock(ReadinessAssessorInterface::class);
        $assessor
            ->shouldReceive('isReady')
            ->with($job->getId())
            ->andReturn(MessageHandlingReadiness::NOW)
        ;

        $resultsClientException = new \Exception('Failed to get results job status');

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);
        $handler = $this->createHandler($assessor, $resultsClient);

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
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createForUserToken(self::$apiToken);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machine = new Machine($job->getId(), 'up/active', 'up');
        $machineRepository->save($machine);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobState = StringValue::random();
        $resultsJobFactory->create(job: $job, state: $resultsJobState);

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'label' => $job->getId(),
                'event_add_url' => 'event/add/results-token',
                'state' => $resultsJobState,
                'end_state' => null,
                'has_events' => false,
            ])),
        ]);

        $assessor = self::getContainer()->get(GetResultsJobReadinessAssessor::class);
        \assert($assessor instanceof ReadinessAssessorInterface);

        $handler = $this->createHandler($assessor, $resultsClient);

        $handler(new GetResultsJobMessage($job->getId()));

        $events = $this->eventRecorder->all(ResultsJobRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new ResultsJobRetrievedEvent(
                $job->getId(),
                new ResultsJob(
                    $job->getId(),
                    'event/add/results-token',
                    new ResultsJobState(
                        $resultsJobState,
                        null,
                        new ResultsClientMetaState(false, false, true),
                    ),
                    false,
                ),
            ),
            $event
        );
    }

    protected function getHandlerClass(): string
    {
        return GetResultsJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return GetResultsJobMessage::class;
    }

    private function createHandler(
        ReadinessAssessorInterface $readinessAssessor,
        ResultsClient $resultsClient,
    ): GetResultsJobMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $messageStateMutator = self::getContainer()->get(MessageStateMutator::class);
        \assert($messageStateMutator instanceof MessageStateMutator);

        $authenticationTokenProvider = self::getContainer()->get(AuthenticationTokenProvider::class);
        \assert($authenticationTokenProvider instanceof AuthenticationTokenProvider);

        return new GetResultsJobMessageHandler(
            $readinessAssessor,
            $messageStateMutator,
            $resultsClient,
            $eventDispatcher,
            $authenticationTokenProvider,
        );
    }
}
