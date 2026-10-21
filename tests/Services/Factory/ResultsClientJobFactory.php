<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Tests\Services\Generator\StringValue;
use SmartAssert\ResultsClient\Model\CreatedJob;
use SmartAssert\ResultsClient\Model\CreatedJobInterface;
use SmartAssert\ResultsClient\Model\Job;
use SmartAssert\ResultsClient\Model\JobInterface;
use SmartAssert\ResultsClient\Model\JobState;
use SmartAssert\ResultsClient\Model\MetaState;

class ResultsClientJobFactory
{
    /**
     * @param non-empty-string   $label
     * @param non-empty-string   $state
     * @param ?non-empty-string  $endState
     * @param non-empty-string[] $previousStates
     */
    public static function createRetrievedJob(
        string $label,
        string $state,
        ?string $endState = null,
        ?MetaState $metaState = null,
        array $previousStates = [],
    ): JobInterface {
        $metaState = $metaState ?? new MetaState(false, false, false);

        return new Job($label, new JobState($state, $endState, $metaState), false, $previousStates);
    }

    public static function createRandomRetrievedJob(): JobInterface
    {
        return self::createRetrievedJob(
            StringValue::random(),
            StringValue::random(),
        );
    }

    public static function createCreateJob(
        JobInterface $job,
        string $eventNotifyUrl,
        string $eventNotifyToken,
    ): CreatedJobInterface {
        return new CreatedJob($job, $eventNotifyUrl, $eventNotifyToken);
    }

    public static function createRandomCreatedJob(): CreatedJobInterface
    {
        return self::createCreateJob(
            self::createRandomRetrievedJob(),
            StringValue::random(),
            StringValue::random(),
        );
    }
}
