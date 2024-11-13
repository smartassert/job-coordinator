<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function add(Job $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function store(\App\Model\Job $job): void
    {
        $this->getEntityManager()->persist(
            new Job(
                $job->getId(),
                $job->getUserId(),
                $job->getSuiteId(),
                $job->getMaximumDurationInSeconds()
            )
        );
        $this->getEntityManager()->flush();
    }
}
