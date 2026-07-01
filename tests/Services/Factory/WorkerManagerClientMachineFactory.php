<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Tests\Services\Generator\BoolValue;
use App\Tests\Services\Generator\StringValue;
use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use SmartAssert\WorkerManagerClient\Model\Machine;
use SmartAssert\WorkerManagerClient\Model\MetaState;

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
        bool $hasActiveState,
        bool $hasEndingState,
        MetaState $metaState,
        ?ActionFailure $actionFailure = null,
    ): Machine {
        return new Machine(
            $id,
            $state,
            $stateCategory,
            $ipAddresses,
            $actionFailure,
            $hasActiveState,
            $hasEndingState,
            new MetaState(
                $metaState->ended,
                $metaState->succeeded,
                $metaState->pending,
            )
        );
    }

    /**
     * @param non-empty-string $jobId
     */
    public static function createRandomForJob(string $jobId): Machine
    {
        $hasFailedState = BoolValue::random();
        $hasActiveState = BoolValue::random();
        $hasEndingState = BoolValue::random();
        $hasEnded = BoolValue::random();
        $hasSucceeded = $hasEnded && !$hasFailedState;
        $isPending = !$hasEnded && !$hasActiveState;

        return self::create(
            $jobId,
            StringValue::random(),
            StringValue::random(),
            [],
            $hasActiveState,
            $hasEndingState,
            new MetaState(
                ended: $hasEnded,
                succeeded: $hasSucceeded,
                pending: $isPending,
            ),
        );
    }
}
