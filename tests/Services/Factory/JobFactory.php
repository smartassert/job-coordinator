<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\Job;
use App\Repository\JobRepository;
use Symfony\Component\Uid\Ulid;

readonly class JobFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {
    }

    public function createRandom(): Job
    {
        $id = (string) new Ulid();
        \assert('' !== $id);

        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $maximumDurationInSeconds = rand(1, 1000);

        $job = new Job($id, $userId, $suiteId, $maximumDurationInSeconds);

        $this->jobRepository->add($job);

        return $job;
    }
}
