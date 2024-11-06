<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Job;
use App\Repository\RemoteRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class EntityRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    /**
     * @param non-empty-string $jobId
     */
    public function removeJob(string $jobId): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->delete(Job::class, 'j')
            ->where('j.id = :id')
            ->setParameter('id', $jobId)
        ;

        $query = $queryBuilder->getQuery();
        $query->execute();
    }

    public function removeAllRemoteRequests(): void
    {
        foreach ($this->remoteRequestRepository->findAll() as $remoteRequest) {
            $this->entityManager->remove($remoteRequest);
            $this->entityManager->flush();
        }
    }
}
