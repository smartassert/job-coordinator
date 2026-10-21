<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\ResultsJob;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\ResultsJobRepository;
use App\Tests\Services\Generator\StringValue;

readonly class ResultsJobFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    /**
     * @param null|non-empty-string $eventNotifyUrl
     * @param null|non-empty-string $eventNotifyToken
     * @param null|non-empty-string $state
     * @param null|non-empty-string $endState
     */
    public function create(
        JobInterface $job,
        ?string $eventNotifyUrl = null,
        ?string $eventNotifyToken = null,
        ?string $state = null,
        ?string $endState = null,
        ?MetaState $metaState = null,
    ): ResultsJob {
        $eventNotifyUrl = $eventNotifyUrl ?? StringValue::random();
        $eventNotifyToken = $eventNotifyToken ?? StringValue::random();
        $state = is_string($state) ? $state : StringValue::random();
        $metaState = $metaState ?? new MetaState(false, false, true);

        $resultsJob = new ResultsJob($job->getId(), $eventNotifyUrl, $eventNotifyToken, $state, $endState, $metaState);

        $this->resultsJobRepository->save($resultsJob);

        return $resultsJob;
    }
}
