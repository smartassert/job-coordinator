<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Job;
use App\Repository\JobRepository;
use Symfony\Component\Uid\Ulid;

readonly class JobStore
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function retrieve(string $jobId): ?Job
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
