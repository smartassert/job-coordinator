<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageFailureHandler;

use App\Entity\Job;
use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Entity\SerializedSuite;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Exception\MachineCreationException;
use App\Exception\MachineRetrievalException;
use App\Exception\MachineTerminationException;
use App\Exception\RemoteRequestExceptionInterface;
use App\Exception\ResultsJobCreationException;
use App\Exception\ResultsJobStateRetrievalException;
use App\Exception\SerializedSuiteCreationException;
use App\Exception\SerializedSuiteRetrievalException;
use App\Exception\WorkerJobStartException;
use App\MessageFailureHandler\RemoteRequestExceptionHandler;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;

class RemoteRequestExceptionHandlerTest extends WebTestCase
{
    use RemoteRequestFailureCreationDataProviderTrait;
    use MockeryPHPUnitIntegration;

    private RemoteRequestExceptionHandler $handler;
    private Job $job;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(RemoteRequestExceptionHandler::class);
        \assert($handler instanceof RemoteRequestExceptionHandler);
        $this->handler = $handler;

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $this->job = new Job(md5((string) rand()), md5((string) rand()), 600);
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
     * @dataProvider handleSetRemoteRequestFailureDataProvider
     *
     * @param callable(Job): RemoteRequestExceptionInterface $exceptionCreator
     */
    public function testHandleSetRemoteRequestFailure(
        callable $exceptionCreator,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $exception = $exceptionCreator($this->job);

        $remoteRequest = new RemoteRequest($this->job->id, RemoteRequestType::RESULTS_CREATE, 1);
        $this->remoteRequestRepository->save($remoteRequest);

        self::assertNull($remoteRequest->getFailure());

        $envelope = new Envelope(new \stdClass());

        $this->handler->handle($envelope, $exception);

        self::assertSame(1, $this->remoteRequestFailureRepository->count([]));

        $remoteRequestFailure = $this->remoteRequestFailureRepository->findAll()[0];
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailure);

        $remoteRequestFailureData = $remoteRequestFailure->toArray();

        self::assertSame($expectedType->value, $remoteRequestFailureData['type']);
        self::assertSame($expectedCode, $remoteRequestFailureData['code']);
        self::assertSame($expectedMessage, $remoteRequestFailureData['message']);
    }

    /**
     * @return array<mixed>
     */
    public function handleSetRemoteRequestFailureDataProvider(): array
    {
        $remoteRequestExceptionCases = [
            MachineCreationException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new MachineCreationException($job, $inner);
                };
            },
            MachineRetrievalException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new MachineRetrievalException(
                        $job,
                        new Machine($job->id, md5((string) rand()), md5((string) rand()), []),
                        $inner
                    );
                };
            },
            ResultsJobCreationException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new ResultsJobCreationException($job, $inner);
                };
            },
            SerializedSuiteCreationException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new SerializedSuiteCreationException($job, $inner);
                };
            },
            SerializedSuiteRetrievalException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    $serializedSuite = new SerializedSuite($job->id, md5((string) rand()), 'prepared');

                    return new SerializedSuiteRetrievalException($job, $serializedSuite, $inner);
                };
            },
            WorkerJobStartException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new WorkerJobStartException($job, $inner);
                };
            },
            ResultsJobStateRetrievalException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new ResultsJobStateRetrievalException($job, $inner);
                };
            },
            MachineTerminationException::class => function (\Throwable $inner) {
                return function (Job $job) use ($inner) {
                    return new MachineTerminationException($job, $inner);
                };
            },
        ];

        $innerExceptionCases = $this->remoteRequestFailureCreationDataProvider();

        $testCases = [];

        foreach ($remoteRequestExceptionCases as $exceptionClass => $exceptionCreator) {
            foreach ($innerExceptionCases as $innerExceptionCase) {
                $inner = $innerExceptionCase['throwable'];
                unset($innerExceptionCase['throwable']);

                $testCaseName = sprintf(
                    '%s: "%s" %d "%s"',
                    $exceptionClass,
                    $innerExceptionCase['expectedType']->value,
                    $innerExceptionCase['expectedCode'],
                    $innerExceptionCase['expectedMessage'],
                );

                $testCase = array_merge(
                    ['exceptionCreator' => $exceptionCreator($inner)],
                    $innerExceptionCase,
                );

                $testCases[$testCaseName] = $testCase;
            }
        }

        return $testCases;
    }
}
