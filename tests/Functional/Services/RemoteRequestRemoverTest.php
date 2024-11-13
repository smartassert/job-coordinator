<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
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
                'type' => new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE),
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
        $resultsJobCreateType = new RemoteRequestType(
            JobComponent::RESULTS_JOB,
            RemoteRequestAction::CREATE
        );

        $serializedSuiteCreateType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::CREATE,
        );

        $serializedSuiteRetrieveType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::RETRIEVE,
        );

        $machineCreateType = new RemoteRequestType(
            JobComponent::MACHINE,
            RemoteRequestAction::CREATE,
        );

        return [
            'no remote requests for machine/create' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                    ];
                },
                'type' => $machineCreateType,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
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
        $resultsJobCreateType = new RemoteRequestType(
            JobComponent::RESULTS_JOB,
            RemoteRequestAction::CREATE
        );

        $serializedSuiteCreateType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::CREATE,
        );

        $serializedSuiteRetrieveType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::RETRIEVE,
        );

        $machineCreateType = new RemoteRequestType(
            JobComponent::MACHINE,
            RemoteRequestAction::CREATE,
        );

        return [
            'single remote request for machine/create, no remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                    $machineCreateType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 0),
                    ];
                },
                'type' => $machineCreateType,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                    ];
                },
            ],
            'single remote request for machine/create, has remote request failure' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (
                    string $jobId,
                    array $remoteRequestFailures
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                    $machineCreateType,
                ) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                        (new RemoteRequest($jobId, $machineCreateType, 0))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => $machineCreateType,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
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
        $resultsJobCreateType = new RemoteRequestType(
            JobComponent::RESULTS_JOB,
            RemoteRequestAction::CREATE
        );

        $serializedSuiteCreateType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::CREATE,
        );

        $serializedSuiteRetrieveType = new RemoteRequestType(
            JobComponent::SERIALIZED_SUITE,
            RemoteRequestAction::RETRIEVE,
        );

        $machineCreateType = new RemoteRequestType(
            JobComponent::MACHINE,
            RemoteRequestAction::CREATE,
        );

        return [
            'multiple remote requests for machine/create, no remote request failures' => [
                'remoteRequestFailuresCreator' => function () {
                    return [];
                },
                'remoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                    $machineCreateType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 1),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 2),
                    ];
                },
                'type' => $machineCreateType,
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by single remote request' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (
                    string $jobId,
                    array $remoteRequestFailures
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                    $machineCreateType,
                ) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 1),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                        (new RemoteRequest($jobId, $machineCreateType, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => new RemoteRequestType(
                    JobComponent::MACHINE,
                    RemoteRequestAction::CREATE,
                ),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);

                    return [
                        new RemoteRequest($jobId, $resultsJobCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                    ];
                },
            ],
            'multiple remote requests for machine/create, remote request failure used by multiple remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (
                    string $jobId,
                    array $remoteRequestFailures
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                    $machineCreateType,
                ) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, $resultsJobCreateType, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, $machineCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $machineCreateType, 1),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
                        (new RemoteRequest($jobId, $machineCreateType, 2))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'type' => new RemoteRequestType(
                    JobComponent::MACHINE,
                    RemoteRequestAction::CREATE,
                ),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'expectedRemoteRequestsCreator' => function (
                    string $jobId,
                    array $remoteRequestFailures
                ) use (
                    $resultsJobCreateType,
                    $serializedSuiteCreateType,
                    $serializedSuiteRetrieveType,
                ) {
                    \assert('' !== $jobId);
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest($jobId, $resultsJobCreateType, 0))
                            ->setFailure($remoteRequestFailure),
                        new RemoteRequest($jobId, $serializedSuiteCreateType, 0),
                        new RemoteRequest($jobId, $serializedSuiteRetrieveType, 0),
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
