<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerComponentState>
 */
class WorkerComponentStateRepository extends ServiceEntityRepository implements JobComponentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerComponentState::class);
    }

    public function save(WorkerComponentState $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function getApplicationState(Job $job): ?WorkerComponentState
    {
        return $this->findOneBy([
            'jobId' => $job->id,
            'componentName' => WorkerComponentName::APPLICATION,
        ]);
    }
}
