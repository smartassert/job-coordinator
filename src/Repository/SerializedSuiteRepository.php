<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SerializedSuite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SerializedSuite>
 */
class SerializedSuiteRepository extends ServiceEntityRepository implements JobComponentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SerializedSuite::class);
    }

    public function save(SerializedSuite $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function has(string $jobId): bool
    {
        return $this->count(['jobId' => $jobId]) > 0;
    }

    public function get(string $jobId): ?SerializedSuite
    {
        $entity = $this->findOneBy(['jobId' => $jobId]);
        if (null === $entity || '' === $entity->getState()) {
            return null;
        }

        return $entity;
    }
}
