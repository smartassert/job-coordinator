<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\ResultsClient\Model\Job;
use SmartAssert\ResultsClient\Model\JobState;

class ResultsClientJobFactory
{
    /**
     * @param non-empty-string  $label
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public static function create(string $label, string $token, string $state, ?string $endState): Job
    {
        return new Job($label, $token, new JobState($state, $endState));
    }

    public static function createRandom(): Job
    {
        return self::create(
            md5((string) rand()),
            md5((string) rand()),
            md5((string) rand()),
            null
        );
    }
}
