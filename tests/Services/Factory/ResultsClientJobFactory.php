<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Tests\Services\Generator\StringValue;
use SmartAssert\ResultsClient\Model\Job;
use SmartAssert\ResultsClient\Model\JobState;
use SmartAssert\ResultsClient\Model\MetaState;

class ResultsClientJobFactory
{
    /**
     * @param non-empty-string  $label
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public static function create(
        string $label,
        string $token,
        string $state,
        ?string $endState = null,
        ?MetaState $metaState = null,
    ): Job {
        $metaState = $metaState ?? new MetaState(false, false, false);

        return new Job($label, $token, new JobState($state, $endState, $metaState), false);
    }

    public static function createRandom(): Job
    {
        return self::create(
            StringValue::random(),
            StringValue::random(),
            StringValue::random(),
        );
    }
}
