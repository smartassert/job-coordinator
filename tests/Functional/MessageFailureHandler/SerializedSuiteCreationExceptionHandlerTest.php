<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Exception\SerializedSuiteCreationException;
use App\MessageFailureHandler\SerializedSuiteCreationExceptionHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SerializedSuiteCreationExceptionHandlerTest extends WebTestCase
{
    use RemoteRequestFailureCreationDataProviderTrait;
    use MockeryPHPUnitIntegration;

    private SerializedSuiteCreationExceptionHandler $handler;
    private Job $job;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(SerializedSuiteCreationExceptionHandler::class);
        \assert($handler instanceof SerializedSuiteCreationExceptionHandler);
        $this->handler = $handler;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $this->job = new Job(md5((string) rand()), md5((string) rand()), md5((string) rand()), 600);
        $jobRepository->add($this->job);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        $this->remoteRequestRepository = $remoteRequestRepository;

        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;

        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $remoteRequestFailureRepository->remove($entity);
        }
    }

    /**
     * @dataProvider handleDataProvider
     *
     * @param callable(Job): \Throwable $throwableCreator
     */
    public function testHandle(callable $throwableCreator, RequestState $expectedMachineRequestState): void
    {
        self::assertSame(RequestState::UNKNOWN, $this->job->getSerializedSuiteRequestState());

        $this->handler->handle($throwableCreator($this->job));

        self::assertSame($expectedMachineRequestState, $this->job->getSerializedSuiteRequestState());
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
                'expectedMachineRequestState' => RequestState::UNKNOWN,
            ],
            SerializedSuiteCreationException::class => [
                'throwableCreator' => function (Job $job) {
                    return new SerializedSuiteCreationException($job, new \Exception());
                },
                'expectedMachineRequestState' => RequestState::FAILED,
            ],
        ];
    }

    /**
     * @dataProvider remoteRequestFailureCreationDataProvider
     */
    public function testHandleSetRemoteRequestFailure(
        \Throwable $throwable,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $remoteRequest = new RemoteRequest($this->job->id, RemoteRequestType::RESULTS_CREATE, 1);
        $this->remoteRequestRepository->save($remoteRequest);

        self::assertNull($remoteRequest->getFailure());

        $this->handler->handle(new SerializedSuiteCreationException($this->job, $throwable));

        self::assertSame(1, $this->remoteRequestFailureRepository->count([]));

        $remoteRequestFailure = $this->remoteRequestFailureRepository->findAll()[0];
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailure);

        $remoteRequestFailureData = $remoteRequestFailure->jsonSerialize();

        self::assertSame($expectedType->value, $remoteRequestFailureData['type']);
        self::assertSame($expectedCode, $remoteRequestFailureData['code']);
        self::assertSame($expectedMessage, $remoteRequestFailureData['message']);
    }
}
