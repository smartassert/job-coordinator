<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RemoteRequestFailure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RemoteRequestFailure>
 */
class RemoteRequestFailureRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($registry, RemoteRequestFailure::class);
    }

    public function save(RemoteRequestFailure $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function remove(RemoteRequestFailure $entity): void
    {
        if ($this->remoteRequestRepository->hasAnyWithFailure($entity)) {
            return;
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
