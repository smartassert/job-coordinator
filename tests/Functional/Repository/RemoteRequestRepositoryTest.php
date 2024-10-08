<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestFailureType;
use App\Enum\RemoteRequestType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestRepositoryTest extends WebTestCase
{
    private RemoteRequestFailureRepository $remoteRequestFailureRepository;
    private RemoteRequestRepository $remoteRequestRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $remoteRequestRepository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($remoteRequestRepository instanceof RemoteRequestRepository);
        foreach ($remoteRequestRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->remoteRequestRepository = $remoteRequestRepository;

        $remoteRequestFailureRepository = self::getContainer()->get(RemoteRequestFailureRepository::class);
        \assert($remoteRequestFailureRepository instanceof RemoteRequestFailureRepository);
        foreach ($remoteRequestFailureRepository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->remoteRequestFailureRepository = $remoteRequestFailureRepository;
    }

    /**
     * @param RemoteRequest[] $existingRemoteRequests
     */
    #[DataProvider('getLargestIndexDataProvider')]
    public function testGetLargestIndex(
        array $existingRemoteRequests,
        string $jobId,
        RemoteRequestType $type,
        ?int $expected
    ): void {
        foreach ($existingRemoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        self::assertSame($expected, $this->remoteRequestRepository->getLargestIndex($jobId, $type));
    }

    /**
     * @return array<mixed>
     */
    public static function getLargestIndexDataProvider(): array
    {
        $jobId = md5((string) rand());

        return [
            'no existing remote requests' => [
                'existingRemoteRequests' => [],
                'jobId' => $jobId,
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => null,
            ],
            'no existing remote requests for job' => [
                'existingRemoteRequests' => (function () {
                    $jobId = md5((string) rand());

                    return [
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 1),
                        new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 2),
                    ];
                })(),
                'jobId' => $jobId,
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => null,
            ],
            'no existing remote requests for type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest($jobId, RemoteRequestType::MACHINE_CREATE, 0),
                    new RemoteRequest($jobId, RemoteRequestType::MACHINE_GET, 0),
                    new RemoteRequest($jobId, RemoteRequestType::SERIALIZED_SUITE_GET, 0),
                ],
                'jobId' => $jobId,
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => null,
            ],
            'single existing request for job and type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                ],
                'jobId' => $jobId,
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => 0,
            ],
            'multiple existing requests for job and type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 0),
                    new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 1),
                    new RemoteRequest($jobId, RemoteRequestType::RESULTS_CREATE, 2),
                ],
                'jobId' => $jobId,
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => 2,
            ],
        ];
    }

    /**
     * @param callable(): RemoteRequestFailure[]                $remoteRequestFailuresCreator
     * @param callable(RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     */
    #[DataProvider('hasAnyWithFailureDataProvider')]
    public function testHasAnyWithFailure(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        string $remoteRequestFailureId,
        bool $expected,
    ): void {
        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $remoteRequestFailure = $this->remoteRequestFailureRepository->find($remoteRequestFailureId);
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailure);

        self::assertSame($expected, $this->remoteRequestRepository->hasAnyWithFailure($remoteRequestFailure));
    }

    /**
     * @return array<mixed>
     */
    public static function hasAnyWithFailureDataProvider(): array
    {
        return [
            'single remote request failure, no remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expected' => false,
            ],
            'multiple remote request failures, no remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 503, null),
                    ];
                },
                'remoteRequestsCreator' => function () {
                    return [];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expected' => false,
            ],
            'single remote request failure in use by single remote request' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (array $remoteRequestFailures) {
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expected' => true,
            ],
            'single remote request failure in use by multiple remote requests' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (array $remoteRequestFailures) {
                    $remoteRequestFailure = $remoteRequestFailures[0] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expected' => true,
            ],
            'multiple remote request failures, specific remote request failure not in use' => [
                'remoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
                'remoteRequestsCreator' => function (array $remoteRequestFailures) {
                    $remoteRequestFailure = $remoteRequestFailures[1] ?? null;
                    \assert($remoteRequestFailure instanceof RemoteRequestFailure);

                    return [
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                        (new RemoteRequest(md5((string) rand()), RemoteRequestType::MACHINE_CREATE, 0))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expected' => false,
            ],
        ];
    }
}
