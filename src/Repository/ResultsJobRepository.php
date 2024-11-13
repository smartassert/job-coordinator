<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResultsJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResultsJob>
 */
class ResultsJobRepository extends ServiceEntityRepository implements JobComponentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultsJob::class);
    }

    public function save(ResultsJob $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function has(string $jobId): bool
    {
        return $this->count(['jobId' => $jobId]) > 0;
    }
}
