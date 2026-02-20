<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\ResultsJob;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\ResultsJobRepository;

readonly class ResultsJobFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    /**
     * @param null|non-empty-string $token
     * @param null|non-empty-string $state
     * @param null|non-empty-string $endState
     */
    public function create(
        JobInterface $job,
        ?string $token = null,
        ?string $state = null,
        ?string $endState = null,
        ?MetaState $metaState = null,
    ): ResultsJob {
        $token = $token ?? md5((string) rand());
        $state = is_string($state) ? $state : md5((string) rand());
        $metaState = $metaState ?? new MetaState(false, false);

        $resultsJob = new ResultsJob($job->getId(), $token, $state, $endState, $metaState);

        $this->resultsJobRepository->save($resultsJob);

        return $resultsJob;
    }
}
