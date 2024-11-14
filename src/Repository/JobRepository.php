<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Job;
use App\Model\Job as JobModel;
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

    public function store(JobModel $job): void
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
