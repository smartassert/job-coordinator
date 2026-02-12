<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\MessageHandlerNotReadyException;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use App\Tests\Services\Mock\ReadinessAssessorFactory;
use GuzzleHttp\Psr7\Response;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use SmartAssert\ResultsClient\Model\MetaState as ResultsClientMetaState;
use Symfony\Component\Uid\Ulid;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    public function testInvokeNotHandleable(): void
    {
        $jobId = (string) new Ulid();
        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);
        $assessor = ReadinessAssessorFactory::create(
            $message->getRemoteRequestType(),
            $message->getJobId(),
            MessageHandlingReadiness::NEVER
        );

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $handler = $this->createHandler($assessor, $resultsClient);
        $exception = null;

        try {
            $handler($message);
        } catch (MessageHandlerNotReadyException $exception) {
        }

        self::assertInstanceOf(MessageHandlerNotReadyException::class, $exception);
        self::assertSame(MessageHandlingReadiness::NEVER, $exception->getReadiness());
        self::assertSame($exception->getHandlerMessage(), $message);

        self::assertSame([], $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class));
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobId = (string) new Ulid();
        $message = new GetResultsJobStateMessage(self::$apiToken, $jobId);
        $assessor = ReadinessAssessorFactory::create(
            $message->getRemoteRequestType(),
            $message->getJobId(),
            MessageHandlingReadiness::NOW
        );

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
        $job = $jobFactory->createRandom();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machine = new Machine($job->getId(), 'up/active', 'up', false, false);
        $machineRepository->save($machine);

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobState = md5((string) rand());
        $resultsJobFactory->create(job: $job, state: $resultsJobState);

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'state' => $resultsJobState,
                'end_state' => null,
            ])),
        ]);

        $assessor = self::getContainer()->get(ReadinessAssessorInterface::class);
        \assert($assessor instanceof ReadinessAssessorInterface);

        $handler = $this->createHandler($assessor, $resultsClient);

        $handler(new GetResultsJobStateMessage(self::$apiToken, $job->getId()));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new ResultsJobStateRetrievedEvent(
                self::$apiToken,
                $job->getId(),
                new ResultsJobState($resultsJobState, null, new ResultsClientMetaState(false, false))
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
        ReadinessAssessorInterface $readinessAssessor,
        ResultsClient $resultsClient,
    ): GetResultsJobStateMessageHandler {
        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        return new GetResultsJobStateMessageHandler($resultsClient, $eventDispatcher, $readinessAssessor);
    }
}
