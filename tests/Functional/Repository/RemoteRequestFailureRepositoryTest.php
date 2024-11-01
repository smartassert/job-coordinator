<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\RemoteRequest;
use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Enum\RemoteRequestFailureType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RemoteRequestFailureRepositoryTest extends WebTestCase
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
     * @param callable(): RemoteRequestFailure[]                $remoteRequestFailuresCreator
     * @param callable(RemoteRequestFailure[]): RemoteRequest[] $remoteRequestsCreator
     * @param callable(): RemoteRequestFailure[]                $expectedRemoteRequestFailuresCreator
     */
    #[DataProvider('removeDataProvider')]
    public function testRemove(
        callable $remoteRequestFailuresCreator,
        callable $remoteRequestsCreator,
        string $remoteRequestFailureId,
        callable $expectedRemoteRequestFailuresCreator,
    ): void {
        $remoteRequestFailures = $remoteRequestFailuresCreator();
        foreach ($remoteRequestFailures as $remoteRequestFailure) {
            $this->remoteRequestFailureRepository->save($remoteRequestFailure);
        }

        $remoteRequests = $remoteRequestsCreator($remoteRequestFailures);
        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->save($remoteRequest);
        }

        $remoteRequestFailureToRemove = $this->remoteRequestFailureRepository->find($remoteRequestFailureId);
        self::assertInstanceOf(RemoteRequestFailure::class, $remoteRequestFailureToRemove);

        $this->remoteRequestFailureRepository->remove($remoteRequestFailureToRemove);

        $expectedRemoteRequestFailures = $expectedRemoteRequestFailuresCreator();
        self::assertEquals($expectedRemoteRequestFailures, $this->remoteRequestFailureRepository->findAll());
    }

    /**
     * @return array<mixed>
     */
    public static function removeDataProvider(): array
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [];
                },
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
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 503, null),
                    ];
                },
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
                        (new RemoteRequest(
                            md5((string) rand()),
                            RemoteRequestEntity::MACHINE,
                            RemoteRequestAction::CREATE,
                            0
                        ))
                            ->setFailure($remoteRequestFailure),
                    ];
                },
                'remoteRequestFailureId' => RemoteRequestFailure::generateId(RemoteRequestFailureType::HTTP, 404, null),
                'expectedRemoteRequestFailuresCreator' => function () {
                    return [
                        new RemoteRequestFailure(RemoteRequestFailureType::HTTP, 404, null),
                    ];
                },
            ],
        ];
    }
}
