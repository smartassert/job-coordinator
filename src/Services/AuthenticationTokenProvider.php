<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobInterface;
use App\Repository\JobRepository;

readonly class AuthenticationTokenProvider
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    public function get(JobInterface $job): ?string
    {
        $job = $this->jobRepository->findOneBy(['id' => $job->getId()]);

        return $job?->getToken();
    }
}
