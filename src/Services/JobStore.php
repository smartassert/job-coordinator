<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job as JobEntity;
use App\Model\Job;
use App\Model\JobInterface;
use App\Repository\JobRepository;
use Symfony\Component\Uid\Ulid;

readonly class JobStore
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function retrieve(string $jobId): ?JobInterface
    {
        $entity = $this->jobRepository->find($jobId);
        if (null === $entity) {
            return null;
        }

        if ('' === $entity->getId()) {
            return null;
        }

        if ('' === $entity->getUserId()) {
            return null;
        }

        if ('' === $entity->getSuiteId()) {
            return null;
        }

        return new Job(
            $entity->getId(),
            $entity->getUserId(),
            $entity->getSuiteId(),
            $entity->getMaximumDurationInSeconds(),
        );
    }

    /**
     * @param JobEntity[] $jobEntities
     *
     * @return JobInterface[]
     */
    public function hydrateFromJobEntities(array $jobEntities): array
    {
        $jobs = [];

        foreach ($jobEntities as $entity) {
            $job = $this->hydrateFromJobEntity($entity);
            if (null !== $job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @param non-empty-string $userId
     * @param non-empty-string $suiteId
     * @param positive-int     $maximumDurationInSeconds
     */
    public function create(string $userId, string $suiteId, int $maximumDurationInSeconds): Job
    {
        $job = new Job(
            self::generateId(),
            $userId,
            $suiteId,
            $maximumDurationInSeconds
        );

        $this->jobRepository->store($job);

        return $job;
    }

    private function hydrateFromJobEntity(JobEntity $entity): ?Job
    {
        if ('' === $entity->getId()) {
            return null;
        }

        if ('' === $entity->getUserId()) {
            return null;
        }

        if ('' === $entity->getSuiteId()) {
            return null;
        }

        return new Job(
            $entity->getId(),
            $entity->getUserId(),
            $entity->getSuiteId(),
            $entity->getMaximumDurationInSeconds(),
        );
    }

    /**
     * @return non-empty-string
     */
    private function generateId(): string
    {
        $id = (string) new Ulid();

        while ('' === $id) {
            $id = (string) new Ulid();
        }

        return $id;
    }
}
