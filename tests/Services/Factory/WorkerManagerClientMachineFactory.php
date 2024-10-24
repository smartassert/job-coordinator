<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use SmartAssert\WorkerManagerClient\Model\Machine;

class WorkerManagerClientMachineFactory
{
    /**
     * @param non-empty-string   $id
     * @param non-empty-string   $state
     * @param non-empty-string   $stateCategory
     * @param non-empty-string[] $ipAddresses
     */
    public static function create(
        string $id,
        string $state,
        string $stateCategory,
        array $ipAddresses,
        bool $hasFailedState,
        bool $hasActiveState,
        bool $hasEndingState,
        bool $hasEndState,
        ?ActionFailure $actionFailure = null,
    ): Machine {
        return new Machine(
            $id,
            $state,
            $stateCategory,
            $ipAddresses,
            $actionFailure,
            $hasFailedState,
            $hasActiveState,
            $hasEndingState,
            $hasEndState,
        );
    }

    /**
     * @param non-empty-string $jobId
     */
    public static function createRandomForJob(string $jobId): Machine
    {
        $hasFailedState = (bool) rand(0, 1);
        $hasActiveState = (bool) rand(0, 1);
        $hasEndingState = (bool) rand(0, 1);
        $hasEndState = (bool) rand(0, 1);

        return self::create(
            $jobId,
            md5((string) rand()),
            md5((string) rand()),
            [],
            $hasFailedState,
            $hasActiveState,
            $hasEndingState,
            $hasEndState,
        );
    }
}
