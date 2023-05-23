<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RemoteRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RemoteRequest>
 *
 * @method null|RemoteRequest find($id, $lockMode = null, $lockVersion = null)
 * @method null|RemoteRequest findOneBy(array $criteria, array $orderBy = null)
 * @method RemoteRequest[]    findAll()
 * @method RemoteRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemoteRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteRequest::class);
    }

    public function save(RemoteRequest $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function remove(RemoteRequest $entity): void
    {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
