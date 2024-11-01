<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\RemoteJobActionException;
use App\Message\GetResultsJobStateMessage;
use App\MessageHandler\GetResultsJobStateMessageHandler;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;
use App\Tests\Services\Factory\HttpMockedResultsClientFactory;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;

class GetResultsJobStateMessageHandlerTest extends AbstractMessageHandlerTestCase
{
    /**
     * @param callable(Job): JobRepository                    $jobRepositoryCreator
     * @param callable(Job): JobPreparationInspectorInterface $jobPreparationInspectorCreator
     * @param callable(Job): ResultsJobRepository             $resultsJobRepositoryCreator
     */
    #[DataProvider('invokeIncorrectStateDataProvider')]
    public function testInvokeIncorrectState(
        callable $jobRepositoryCreator,
        callable $jobPreparationInspectorCreator,
        callable $resultsJobRepositoryCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $jobRepository = $jobRepositoryCreator($job);
        $jobPreparationInspector = $jobPreparationInspectorCreator($job);
        $resultsJobRepository = $resultsJobRepositoryCreator($job);

        $handler = $this->createHandler(
            $jobRepository,
            $jobPreparationInspector,
            $resultsJobRepository,
            HttpMockedResultsClientFactory::create(),
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->id);

        $handler($message);

        self::assertCount(0, $this->eventRecorder);
    }

    /**
     * @return array<mixed>
     */
    public static function invokeIncorrectStateDataProvider(): array
    {
        $jobRepositoryCreator = function (Job $job) {
            $jobRepository = \Mockery::mock(JobRepository::class);
            $jobRepository
                ->shouldReceive('find')
                ->with($job->id)
                ->andReturn($job)
            ;

            return $jobRepository;
        };

        $nonFailedJobPreparationInspector = function (Job $job) {
            $jobPreparationInspector = \Mockery::mock(JobPreparationInspectorInterface::class);
            $jobPreparationInspector
                ->shouldReceive('hasFailed')
                ->with($job)
                ->andReturnFalse()
            ;

            return $jobPreparationInspector;
        };

        return [
            'no job' => [
                'jobRepositoryCreator' => function (Job $job) {
                    $jobRepository = \Mockery::mock(JobRepository::class);
                    $jobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturnNull()
                    ;

                    return $jobRepository;
                },
                'jobPreparationInspectorCreator' => function () {
                    return \Mockery::mock(JobPreparationInspectorInterface::class);
                },
                'resultsJobRepositoryCreator' => function () {
                    return \Mockery::mock(ResultsJobRepository::class);
                },
            ],
            'job preparation has failed' => [
                'jobRepositoryCreator' => $jobRepositoryCreator,
                'jobPreparationInspectorCreator' => function (Job $job) {
                    $jobPreparationInspector = \Mockery::mock(JobPreparationInspectorInterface::class);
                    $jobPreparationInspector
                        ->shouldReceive('hasFailed')
                        ->with($job)
                        ->andReturnTrue()
                    ;

                    return $jobPreparationInspector;
                },
                'resultsJobRepositoryCreator' => function () {
                    return \Mockery::mock(ResultsJobRepository::class);
                },
            ],
            'no results job' => [
                'jobRepositoryCreator' => $jobRepositoryCreator,
                'jobPreparationInspectorCreator' => $nonFailedJobPreparationInspector,
                'resultsJobRepositoryCreator' => function (Job $job) {
                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturnNull()
                    ;

                    return $resultsJobRepository;
                },
            ],
            'results job has end state' => [
                'jobRepositoryCreator' => $jobRepositoryCreator,
                'jobPreparationInspectorCreator' => $nonFailedJobPreparationInspector,
                'resultsJobRepositoryCreator' => function (Job $job) {
                    $resultsJob = new ResultsJob(
                        $job->id,
                        'token',
                        'state',
                        'end-state',
                    );

                    $resultsJobRepository = \Mockery::mock(ResultsJobRepository::class);
                    $resultsJobRepository
                        ->shouldReceive('find')
                        ->with($job->id)
                        ->andReturn($resultsJob)
                    ;

                    return $resultsJobRepository;
                },
            ],
        ];
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJobFactory->createRandomForJob($job);

        $resultsClientException = new \Exception('Failed to get results job status');

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $resultsClient = HttpMockedResultsClientFactory::create([$resultsClientException]);

        $handler = $this->createHandler(
            $jobRepository,
            $jobPreparationInspector,
            $resultsJobRepository,
            $resultsClient
        );

        $message = new GetResultsJobStateMessage(self::$apiToken, $job->id);

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
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJob = $resultsJobFactory->createRandomForJob($job);

        $resultsClient = HttpMockedResultsClientFactory::create([
            new Response(200, ['content-type' => 'application/json'], (string) json_encode([
                'state' => $resultsJob->getState(),
                'end_state' => $resultsJob->getEndState(),
            ])),
        ]);

        $jobPreparationInspector = self::getContainer()->get(JobPreparationInspectorInterface::class);
        \assert($jobPreparationInspector instanceof JobPreparationInspectorInterface);

        $handler = $this->createHandler(
            $jobRepository,
            $jobPreparationInspector,
            $resultsJobRepository,
            $resultsClient
        );

        $handler(new GetResultsJobStateMessage(self::$apiToken, $job->id));

        $events = $this->eventRecorder->all(ResultsJobStateRetrievedEvent::class);
        $event = $events[0] ?? null;

        self::assertEquals(
            new ResultsJobStateRetrievedEvent(
                self::$apiToken,
                $job->id,
                new ResultsJobState($resultsJob->getState(), $resultsJob->getEndState())
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
        JobRepository $jobRepository,
        JobPreparationInspectorInterface $jobPreparationInspector,
        ResultsJobRepository $resultsJobRepository,
        ResultsClient $resultsClient,
    ): GetResultsJobStateMessageHandler {
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
