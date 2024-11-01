<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestIndexGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestIndexGeneratorTest extends WebTestCase
{
    private RemoteRequestIndexGenerator $remoteRequestIndexGenerator;
    private RemoteRequestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $remoteRequestIndexGenerator = self::getContainer()->get(RemoteRequestIndexGenerator::class);
        \assert($remoteRequestIndexGenerator instanceof RemoteRequestIndexGenerator);
        $this->remoteRequestIndexGenerator = $remoteRequestIndexGenerator;

        $repository = self::getContainer()->get(RemoteRequestRepository::class);
        \assert($repository instanceof RemoteRequestRepository);
        foreach ($repository->findAll() as $entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }
        $this->repository = $repository;
    }

    /**
     * @param RemoteRequest[] $existingRemoteRequests
     */
    #[DataProvider('generateDataProvider')]
    public function testGenerate(
        array $existingRemoteRequests,
        string $jobId,
        RemoteRequestEntity $entity,
        RemoteRequestAction $action,
        int $expected
    ): void {
        foreach ($existingRemoteRequests as $remoteRequest) {
            $this->repository->save($remoteRequest);
        }

        self::assertSame($expected, $this->remoteRequestIndexGenerator->generate($jobId, $entity, $action));
    }

    /**
     * @return array<mixed>
     */
    public static function generateDataProvider(): array
    {
        $jobId = md5((string) rand());

        return [
            'no existing remote requests' => [
                'existingRemoteRequests' => [],
                'jobId' => $jobId,
                'entity' => RemoteRequestEntity::RESULTS_JOB,
                'action' => RemoteRequestAction::CREATE,
                'expected' => 0,
            ],
            'no existing remote requests for job' => [
                'existingRemoteRequests' => (function () {
                    $jobId = md5((string) rand());

                    return [
                        new RemoteRequest(
                            $jobId,
                            RemoteRequestEntity::RESULTS_JOB,
                            RemoteRequestAction::CREATE,
                            0,
                        ),
                        new RemoteRequest(
                            $jobId,
                            RemoteRequestEntity::RESULTS_JOB,
                            RemoteRequestAction::CREATE,
                            1,
                        ),
                        new RemoteRequest(
                            $jobId,
                            RemoteRequestEntity::RESULTS_JOB,
                            RemoteRequestAction::CREATE,
                            2,
                        ),
                    ];
                })(),
                'jobId' => $jobId,
                'entity' => RemoteRequestEntity::RESULTS_JOB,
                'action' => RemoteRequestAction::CREATE,
                'expected' => 0,
            ],
            'no existing remote requests for type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::MACHINE,
                        RemoteRequestAction::CREATE,
                        0,
                    ),
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::MACHINE,
                        RemoteRequestAction::RETRIEVE,
                        0,
                    ),
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        RemoteRequestAction::RETRIEVE,
                        0,
                    ),
                ],
                'jobId' => $jobId,
                'entity' => RemoteRequestEntity::RESULTS_JOB,
                'action' => RemoteRequestAction::CREATE,
                'expected' => 0,
            ],
            'single existing request for job and type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::RESULTS_JOB,
                        RemoteRequestAction::CREATE,
                        0,
                    ),
                ],
                'jobId' => $jobId,
                'entity' => RemoteRequestEntity::RESULTS_JOB,
                'action' => RemoteRequestAction::CREATE,
                'expected' => 1,
            ],
            'multiple existing requests for job and type' => [
                'existingRemoteRequests' => [
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::RESULTS_JOB,
                        RemoteRequestAction::CREATE,
                        0,
                    ),
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::RESULTS_JOB,
                        RemoteRequestAction::CREATE,
                        1,
                    ),
                    new RemoteRequest(
                        $jobId,
                        RemoteRequestEntity::RESULTS_JOB,
                        RemoteRequestAction::CREATE,
                        2,
                    ),
                ],
                'jobId' => $jobId,
                'entity' => RemoteRequestEntity::RESULTS_JOB,
                'action' => RemoteRequestAction::CREATE,
                'expected' => 3,
            ],
        ];
    }
}
