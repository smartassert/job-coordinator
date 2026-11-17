<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Model\JobInterface;
use App\Repository\JobRepository;
use App\Request\CreateJobRequest;
use SmartAssert\UsersSecurityBundle\Security\User;
use Symfony\Component\Uid\Ulid;

readonly class JobFactory
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    public function create(User $user, CreateJobRequest $request): JobInterface
    {
        $job = new Job(
            (string) new Ulid(),
            $user->getUserIdentifier(),
            $request->suiteId,
            $request->maximumDurationInSeconds,
            $user->getSecurityToken(),
        );

        $this->jobRepository->store($job);

        return $job;
    }
}
