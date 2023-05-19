<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RemoteRequestFailure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RemoteRequestFailure>
 *
 * @method null|RemoteRequestFailure find($id, $lockMode = null, $lockVersion = null)
 * @method null|RemoteRequestFailure findOneBy(array $criteria, array $orderBy = null)
 * @method RemoteRequestFailure[]    findAll()
 * @method RemoteRequestFailure[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemoteRequestFailureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteRequestFailure::class);
    }

    public function save(RemoteRequestFailure $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RemoteRequestFailure $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
