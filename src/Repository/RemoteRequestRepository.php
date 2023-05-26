<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
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

    /**
     * @return null|int<0, max>
     */
    public function getLargestIndex(string $jobId, RemoteRequestType $type): ?int
    {
        $queryBuilder = $this->createQueryBuilder('RemoteRequest');
        $queryBuilder
            ->select('RemoteRequest.index')
            ->where('RemoteRequest.jobId = :JobId')
            ->andWhere('RemoteRequest.type = :RequestType')
            ->orderBy('RemoteRequest.index', 'DESC')
            ->setParameter('JobId', $jobId)
            ->setParameter('RequestType', $type)
            ->setMaxResults(1)
        ;

        $query = $queryBuilder->getQuery();

        $results = $query->getArrayResult();
        $result = $results[0] ?? null;
        if (!is_array($result)) {
            return null;
        }

        $largestIndex = $result['index'] ?? null;
        if (null === $largestIndex) {
            return null;
        }

        if (!(is_int($largestIndex) && $largestIndex >= 0)) {
            return null;
        }

        return $largestIndex;
    }
}
