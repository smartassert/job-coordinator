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
        ?ActionFailure $actionFailure = null,
    ): Machine {
        return new Machine($id, $state, $stateCategory, $ipAddresses, $actionFailure, $hasFailedState);
    }

    public static function createRandom(): Machine
    {
        $hasFailedState = (bool) rand(0, 1);

        return self::create(md5((string) rand()), md5((string) rand()), md5((string) rand()), [], $hasFailedState);
    }
}
