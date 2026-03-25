<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WorkerJobCreationFailure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerJobCreationFailure>
 */
class WorkerJobCreationFailureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerJobCreationFailure::class);
    }

    public function save(WorkerJobCreationFailure $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }
}
