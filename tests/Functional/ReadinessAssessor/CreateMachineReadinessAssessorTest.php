<?php

declare(strict_types=1);

namespace App\Tests\Functional\ReadinessAssessor;

use App\Entity\Machine;
use App\Entity\RemoteRequest;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RequestState;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\ReadinessAssessor\CreateMachineReadinessHandler;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Factory\JobFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class CreateMachineReadinessAssessorTest extends WebTestCase
{
    private CreateMachineReadinessHandler $assessor;

    protected function setUp(): void
    {
        $assessor = self::getContainer()->get(CreateMachineReadinessHandler::class);
        \assert($assessor instanceof CreateMachineReadinessHandler);

        $this->assessor = $assessor;
    }

    public function testHandles(): void
    {
        self::assertTrue($this->assessor->handles(RemoteRequestType::createForMachineCreation()));

        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobCreation()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForResultsJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForSerializedSuiteRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForWorkerJobRetrieval()));
        self::assertFalse($this->assessor->handles(RemoteRequestType::createForMachineTermination()));
    }

    /**
     * @param callable(JobInterface $job, array<mixed>): void $setup
     */
    #[DataProvider('isReadyDataProvider')]
    public function testIsReady(callable $setup, MessageHandlingReadiness $expected): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $setup(
            $job,
            [
                MachineRepository::class => self::getContainer()->get(MachineRepository::class),
                SerializedSuiteRepository::class => self::getContainer()->get(SerializedSuiteRepository::class),
                ResultsJobRepository::class => self::getContainer()->get(ResultsJobRepository::class),
                RemoteRequestRepository::class => self::getContainer()->get(RemoteRequestRepository::class),
            ]
        );

        self::assertSame($expected, $this->assessor->isReady($job->getId()));
    }

    /**
     * @return array<mixed>
     */
    public static function isReadyDataProvider(): array
    {
        return [
            'machine already exists' => [
                'setup' => function (JobInterface $job, array $services): void {
                    $machineRepository = $services[MachineRepository::class];
                    \assert($machineRepository instanceof MachineRepository);

                    $machineRepository->save(
                        new Machine(
                            $job->getId(),
                            'state',
                            'state-category',
                            false,
                            false,
                        )
                    );
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'serialized suite does not exist' => [
                'setup' => function (): void {},
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite is not prepared' => [
                'setup' => function (JobInterface $job, array $services): void {
                    $serializedSuiteRepository = $services[SerializedSuiteRepository::class];
                    \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'preparing', false, false)
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'serialized suite creation failed' => [
                'setup' => function (JobInterface $job, array $services): void {
                    $remoteRequest = new RemoteRequest(
                        $job->getId(),
                        RemoteRequestType::createForSerializedSuiteCreation()
                    );

                    $remoteRequest->setState(RequestState::FAILED);

                    $remoteRequestRepository = $services[RemoteRequestRepository::class];
                    \assert($remoteRequestRepository instanceof RemoteRequestRepository);

                    $remoteRequestRepository->save($remoteRequest);
                },
                'expected' => MessageHandlingReadiness::NEVER,
            ],
            'results job does not exist' => [
                'setup' => function (JobInterface $job, array $services): void {
                    $serializedSuiteRepository = $services[SerializedSuiteRepository::class];
                    \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true)
                    );
                },
                'expected' => MessageHandlingReadiness::EVENTUALLY,
            ],
            'ready' => [
                'setup' => function (JobInterface $job, array $services): void {
                    $serializedSuiteRepository = $services[SerializedSuiteRepository::class];
                    \assert($serializedSuiteRepository instanceof SerializedSuiteRepository);

                    $resultsJobRepository = $services[ResultsJobRepository::class];
                    \assert($resultsJobRepository instanceof ResultsJobRepository);

                    $serializedSuiteId = (string) new Ulid();

                    $serializedSuiteRepository->save(
                        new SerializedSuite($job->getId(), $serializedSuiteId, 'prepared', true, true)
                    );

                    $resultsJobRepository->save(
                        new ResultsJob($job->getId(), 'token', 'compiling', null)
                    );
                },
                'expected' => MessageHandlingReadiness::NOW,
            ],
        ];
    }
}
