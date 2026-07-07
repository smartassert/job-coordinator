<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\Job;
use App\Model\JobInterface;
use App\Repository\JobRepository;
use App\Tests\Services\Generator\Id;
use Symfony\Component\Uid\Ulid;

readonly class JobFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    public function createRandom(): JobInterface
    {
        $userId = Id::generate();
        $suiteId = Id::generate();

        $maximumDurationInSeconds = rand(1, 1000);

        $job = new Job(
            (string) new Ulid(),
            $userId,
            $suiteId,
            $maximumDurationInSeconds,
        );
        $this->jobRepository->store($job);

        return $job;
    }
}
