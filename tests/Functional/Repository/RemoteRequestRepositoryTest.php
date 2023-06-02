<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestRepositoryTest extends WebTestCase
{
    private RemoteRequestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $repository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($repository instanceof RemoteRequestRepository);
        foreach ($repository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->repository = $repository;
    }

    /**
     * @dataProvider getLargestIndexDataProvider
     *
     * @param RemoteRequest[] $existingRemoteRequests
     */
    public function testGetLargestIndex(
        array $existingRemoteRequests,
        string $jobId,
        RemoteRequestType $type,
        ?int $expected
    ): void {
        foreach ($existingRemoteRequests as $remoteRequest) {
            $this->repository->save($remoteRequest);
        }

        self::assertSame($expected, $this->repository->getLargestIndex($jobId, $type));
    }

    /**
     * @return array<mixed>
     */
    public function getLargestIndexDataProvider(): array
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
}