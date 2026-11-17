<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\JobRepository;

readonly class UserSecurityTokenProvider
{
    public function __construct(
        private JobRepository $jobRepository,
    ) {}

    /**
     * @return ?non-empty-string
     */
    public function get(string $jobId): ?string
    {
        return $this->jobRepository->find($jobId)?->getUserSecurityToken();
    }
}
