<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WorkerState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerState>
 *
 * @method null|WorkerState find($id, $lockMode = null, $lockVersion = null)
 * @method null|WorkerState findOneBy(array $criteria, array $orderBy = null)
 * @method WorkerState[]    findAll()
 * @method WorkerState[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkerStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerState::class);
    }

    public function save(WorkerState $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }
}
