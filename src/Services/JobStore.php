<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Job as JobModel;
use App\Repository\JobRepository;

readonly class JobStore
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function retrieve(string $jobId): ?JobModel
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

        return new JobModel(
            $entity->getId(),
            $entity->getUserId(),
            $entity->getSuiteId(),
            $entity->getMaximumDurationInSeconds(),
        );
    }
}
