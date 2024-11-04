<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SerializedSuite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SerializedSuite>
 *
 * @method null|SerializedSuite find($id, $lockMode = null, $lockVersion = null)
 * @method null|SerializedSuite findOneBy(array $criteria, array $orderBy = null)
 * @method SerializedSuite[]    findAll()
 * @method SerializedSuite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
}
