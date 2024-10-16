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
use App\Message\CreateMachineMessage;
use App\Message\CreateResultsJobMessage;
use App\Message\CreateSerializedSuiteMessage;
use App\Message\GetMachineMessage;
use App\Message\GetResultsJobStateMessage;
use App\Message\GetSerializedSuiteMessage;
use App\Message\StartWorkerJobMessage;
use App\Message\TerminateMachineMessage;
use App\MessageFailureHandler\RemoteRequestExceptionHandler;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Tests\DataProvider\RemoteRequestFailureCreationDataProviderTrait;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\WorkerManagerClientMachineFactory as MachineFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $this->job = $jobFactory->createRandom();

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
     * @param callable(Job): RemoteRequestExceptionInterface $exceptionCreator
     */
    #[DataProvider('handleSetRemoteRequestFailureDataProvider')]
    public function testHandleSetRemoteRequestFailure(
        callable $exceptionCreator,
        RemoteRequestType $remoteRequestType,
        RemoteRequestFailureType $expectedType,
        int $expectedCode,
        string $expectedMessage,
    ): void {
        self::assertSame(0, $this->remoteRequestFailureRepository->count([]));

        $exception = $exceptionCreator($this->job);

        $remoteRequest = new RemoteRequest($this->job->id, $remoteRequestType, 0);
        $this->remoteRequestRepository->save($remoteRequest);

        self::assertNull($remoteRequest->getFailure());

        $envelope = new Envelope(new \stdClass());

        $this->handler->handle($envelope, $exception);

        self::assertSame(1, $this->remoteRequestFailureRepository->count([]));

        $remoteRequestFailure = $this->remoteRequestFailureRepository->findAll()[0];
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailure);

        self::assertEquals(
            new RemoteRequestFailure($expectedType, $expectedCode, $expectedMessage),
            $remoteRequestFailure
        );
    }

    /**
     * @return array<mixed>
     */
    public static function handleSetRemoteRequestFailureDataProvider(): array
    {
        $remoteRequestExceptionCases = [
            MachineCreationException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new MachineCreationException(
                            $job,
                            $inner,
                            new CreateMachineMessage(md5((string) rand()), $job->id),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::MACHINE_CREATE,
            ],
            MachineRetrievalException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        $machine = MachineFactory::create(
                            $job->id,
                            md5((string) rand()),
                            md5((string) rand()),
                            [],
                            false
                        );

                        return new MachineRetrievalException(
                            $job,
                            $machine,
                            $inner,
                            new GetMachineMessage(md5((string) rand()), $job->id, $machine),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::MACHINE_GET,
            ],
            ResultsJobCreationException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new ResultsJobCreationException(
                            $job,
                            $inner,
                            new CreateResultsJobMessage(md5((string) rand()), $job->id),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::RESULTS_CREATE,
            ],
            SerializedSuiteCreationException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new SerializedSuiteCreationException(
                            $job,
                            $inner,
                            new CreateSerializedSuiteMessage(md5((string) rand()), $job->id, []),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::SERIALIZED_SUITE_CREATE,
            ],
            SerializedSuiteRetrievalException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        $serializedSuiteId = md5((string) rand());

                        $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, 'prepared');

                        return new SerializedSuiteRetrievalException(
                            $job,
                            $serializedSuite,
                            $inner,
                            new GetSerializedSuiteMessage(md5((string) rand()), $job->id, $serializedSuiteId),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::SERIALIZED_SUITE_GET,
            ],
            WorkerJobStartException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new WorkerJobStartException(
                            $job,
                            $inner,
                            new StartWorkerJobMessage(md5((string) rand()), $job->id, '127.0.0.1'),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::MACHINE_START_JOB,
            ],
            ResultsJobStateRetrievalException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new ResultsJobStateRetrievalException(
                            $job,
                            $inner,
                            new GetResultsJobStateMessage(md5((string) rand()), $job->id),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::RESULTS_STATE_GET,
            ],
            MachineTerminationException::class => [
                'exceptionCreator' => function (\Throwable $inner) {
                    return function (Job $job) use ($inner) {
                        return new MachineTerminationException(
                            $job,
                            $inner,
                            new TerminateMachineMessage(md5((string) rand()), $job->id),
                        );
                    };
                },
                'remoteRequestType' => RemoteRequestType::MACHINE_TERMINATE,
            ],
        ];

        $innerExceptionCases = self::remoteRequestFailureCreationDataProvider();

        $testCases = [];
        foreach ($remoteRequestExceptionCases as $exceptionClass => $testCaseProperties) {
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
                    [
                        'exceptionCreator' => ($testCaseProperties['exceptionCreator'])($inner),
                        'remoteRequestType' => $testCaseProperties['remoteRequestType'],
                    ],
                    $innerExceptionCase,
                );

                $testCases[$testCaseName] = $testCase;
            }
        }

        return $testCases;
    }
}
