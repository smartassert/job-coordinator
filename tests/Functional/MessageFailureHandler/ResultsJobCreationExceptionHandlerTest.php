<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\Entity\Job;
use App\Enum\RequestState;
use App\Exception\ResultsJobCreationException;
use App\MessageFailureHandler\ResultsJobCreationExceptionHandler;
use App\Repository\JobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResultsJobCreationExceptionHandlerTest extends WebTestCase
{
    use MockeryPHPUnitIntegration;

    private ResultsJobCreationExceptionHandler $handler;
    private JobRepository $jobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(ResultsJobCreationExceptionHandler::class);
        \assert($handler instanceof ResultsJobCreationExceptionHandler);
        $this->handler = $handler;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;
    }

    /**
     * @dataProvider handleDataProvider
     *
     * @param callable(Job): \Throwable $throwableCreator
     */
    public function testHandle(callable $throwableCreator, RequestState $expectedResultsJobRequestState): void
    {
        $job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $this->jobRepository->add($job);
        self::assertSame(RequestState::UNKNOWN, $job->getResultsJobRequestState());

        $this->handler->handle($throwableCreator($job));

        self::assertSame($expectedResultsJobRequestState, $job->getResultsJobRequestState());
    }

    /**
     * @return array<mixed>
     */
    public function handleDataProvider(): array
    {
        return [
            'unhandled exception' => [
                'throwableCreator' => function () {
                    return new \Exception();
                },
                'expectedResultsJobRequestState' => RequestState::UNKNOWN,
            ],
            ResultsJobCreationException::class => [
                'throwableCreator' => function (Job $job) {
                    return new ResultsJobCreationException($job, new \Exception());
                },
                'expectedResultsJobRequestState' => RequestState::FAILED,
            ],
        ];
    }
}
