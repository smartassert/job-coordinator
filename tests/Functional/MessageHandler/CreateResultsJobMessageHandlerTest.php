<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Job;
use App\Enum\ResultsJobCreationState;
use App\Exception\ResultsJobCreationException;
use App\Message\CreateResultsJobMessage;
use App\MessageHandler\CreateResultsJobMessageHandler;
use App\Repository\JobRepository;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
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

        $handler = $this->createHandler(
            jobRepository: $jobRepository,
        );

        $message = new CreateResultsJobMessage(self::$apiToken, $jobId);

        $handler($message);

        $this->assertNoMessagesDispatched();
    }

    public function testInvokeResultsClientThrowsException(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'prepared',
            serializedSuiteId: md5((string) rand()),
        );
        self::assertSame(ResultsJobCreationState::UNKNOWN, $job->getResultsJobCreationState());

        $resultsClientException = new \Exception('Failed to create results job');

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('createJob')
            ->with(self::$apiToken, $job->id)
            ->andThrow($resultsClientException)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        $message = new CreateResultsJobMessage(self::$apiToken, $jobId);

        try {
            $handler($message);
            self::fail(ResultsJobCreationException::class . ' not thrown');
        } catch (ResultsJobCreationException $e) {
            self::assertSame($resultsClientException, $e->previousException);
            $this->assertNoMessagesDispatched();
        }

        self::assertSame(ResultsJobCreationState::HALTED, $job->getResultsJobCreationState());
    }

    public function testInvokeSuccess(): void
    {
        $jobId = md5((string) rand());
        $job = $this->createJob(
            jobId: $jobId,
            serializedSuiteState: 'prepared',
            serializedSuiteId: md5((string) rand()),
        );
        self::assertSame(ResultsJobCreationState::UNKNOWN, $job->getResultsJobCreationState());

        $resultsJob = new ResultsJob($jobId, md5((string) rand()));

        $resultsClient = \Mockery::mock(ResultsClient::class);
        $resultsClient
            ->shouldReceive('createJob')
            ->with(self::$apiToken, $job->id)
            ->andReturn($resultsJob)
        ;

        $handler = $this->createHandler(
            resultsClient: $resultsClient,
        );

        self::assertNull($job->getResultsToken());

        $handler(new CreateResultsJobMessage(self::$apiToken, $jobId));

        self::assertSame(ResultsJobCreationState::SUCCEEDED, $job->getResultsJobCreationState());
        self::assertSame($resultsJob->token, $job->getResultsToken());
        $this->assertNoMessagesDispatched();
    }

    protected function getHandlerClass(): string
    {
        return CreateResultsJobMessageHandler::class;
    }

    protected function getHandledMessageClass(): string
    {
        return CreateResultsJobMessage::class;
    }

    /**
     * @param non-empty-string  $jobId
     * @param ?non-empty-string $resultsToken
     * @param ?non-empty-string $serializedSuiteState
     * @param ?non-empty-string $serializedSuiteId
     */
    private function createJob(
        string $jobId,
        ?string $resultsToken = null,
        ?string $serializedSuiteState = null,
        ?string $serializedSuiteId = null,
    ): Job {
        $job = new Job(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            600
        );

        if (is_string($resultsToken)) {
            $job = $job->setResultsToken($resultsToken);
        }

        if (is_string($serializedSuiteState)) {
            $job = $job->setSerializedSuiteState($serializedSuiteState);
        }

        if (is_string($serializedSuiteId)) {
            $job = $job->setSerializedSuiteId($serializedSuiteId);
        }

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add($job);

        return $job;
    }

    private function createHandler(
        ?JobRepository $jobRepository = null,
        ?ResultsClient $resultsClient = null,
    ): CreateResultsJobMessageHandler {
        $messageBus = self::getContainer()->get(MessageBusInterface::class);
        \assert($messageBus instanceof MessageBusInterface);

        if (null === $jobRepository) {
            $jobRepository = self::getContainer()->get(JobRepository::class);
            \assert($jobRepository instanceof JobRepository);
        }

        if (null === $resultsClient) {
            $resultsClient = self::getContainer()->get(ResultsClient::class);
            \assert($resultsClient instanceof ResultsClient);
        }

        return new CreateResultsJobMessageHandler($jobRepository, $resultsClient);
    }
}
