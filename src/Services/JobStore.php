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

        return $this->hydrateFromJobEntity($entity);
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
        if ('' === $entity->id) {
            return null;
        }

        if ('' === $entity->userId) {
            return null;
        }

        if ('' === $entity->suiteId) {
            return null;
        }

        if ($entity->maximumDurationInSeconds < 1) {
            return null;
        }

        return new Job($entity->id, $entity->userId, $entity->suiteId, $entity->maximumDurationInSeconds);
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
