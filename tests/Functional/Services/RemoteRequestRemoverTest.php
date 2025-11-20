<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestRemover;
use App\Tests\Services\Factory\JobFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class RemoteRequestRemoverTest extends WebTestCase
{
    private RemoteRequestRemover $remoteRequestRemover;
    private RemoteRequestRepository $remoteRequestRepository;
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $remoteRequestRemover = self::getContainer()->get(RemoteRequestRemover::class);
        \assert($remoteRequestRemover instanceof RemoteRequestRemover);
        $this->remoteRequestRemover = $remoteRequestRemover;

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $remoteRequestRepository->remove($entity);
        }
        $this->remoteRequestRepository = $remoteRequestRepository;

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $remoteRequestFailureRepository->remove($entity);
        }
        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
    }

    /**
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    #[DataProvider('noRemoteRequestsDataProvider')]
    public function testRemoveForJobAndTypeNoJob(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $jobId = (string) new Ulid();

        $this->doRemoteRequestRemoverTest(
            $jobId,
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($jobId, $type) {
                $this->remoteRequestRemover->removeForJobAndType($jobId, $type);
            }
        );
    }

    /**
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    #[DataProvider('noRemoteRequestsDataProvider')]
    #[DataProvider('noRemoteRequestsForTypeDataProvider')]
    #[DataProvider('singleRequestForTypeDataProvider')]
    #[DataProvider('multipleRequestsForTypeDataProvider')]
    public function testRemoveForJobAndType(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        RemoteRequestType $type,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
    ): void {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $this->doRemoteRequestRemoverTest(
            $job->getId(),
            $remoteRequestFailuresCreator,
            $remoteRequestsCreator,
            $expectedRemoteRequestFailuresCreator,
            $expectedRemoteRequestsCreator,
            function () use ($job, $type) {
                $this->remoteRequestRemover->removeForJobAndType($job->getId(), $type);
            }
        );
    }

    /**
     * @return array<mixed>
     */
    public static function noRemoteRequestsDataProvider(): array
    {
        return [
            'no remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function () {
                    return [];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function noRemoteRequestsForTypeDataProvider(): array
    {
        return [
            'no remote requests for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function singleRequestForTypeDataProvider(): array
    {
        return [
            'single remote request for machine/create, no remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
            'single remote request for machine/create, has remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0)
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function multipleRequestsForTypeDataProvider(): array
    {
        return [
            'multiple remote requests for machine/create, no remote request failures' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 1),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 2),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by single remote request' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 1),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 2)
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by multiple remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 1),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForMachineCreation(), 2)
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => RemoteRequestType::createForMachineCreation(),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'expectedRemoteRequestsCreator' => function (string $jobId, array $remoteRequestFailures) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::createForResultsJobCreation(), 0)
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteCreation(), 0),
                        new RemoteRequest($jobId, RemoteRequestType::createForSerializedSuiteRetrieval(), 0),
                    ];
                },
            ],
        ];
    }

    /**
     * @param callable(): RemoteRequestFailure[]                        $remoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                        $expectedRemoteRequestFailuresCreator
     * @param callable(string, RemoteRequestFailure[]): RemoteRequest[] $expectedRemoteRequestsCreator
     */
    private function doRemoteRequestRemoverTest(
        string $jobId,
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        callable $expectedRemoteRequestFailuresCreator,
        callable $expectedRemoteRequestsCreator,
        callable $action,
    ): void {
        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($jobId, $remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $action();

        self::assertEquals($expectedRemoteRequestFailuresCreator(), $this->remoteRequestFailureRepository->findAll());
        self::assertEquals(
            $expectedRemoteRequestsCreator($jobId, $remoteRequestFailures),
            $this->remoteRequestRepository->findAll()
        );
    }
}
