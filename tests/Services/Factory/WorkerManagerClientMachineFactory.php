<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\WorkerManagerClient\Model\Machine;

class WorkerManagerClientMachineFactory
{
    /**
     * @param non-empty-string   $id
     * @param non-empty-string   $state
     * @param non-empty-string   $stateCategory
     * @param non-empty-string[] $ipAddresses
     */
    public static function create(string $id, string $state, string $stateCategory, array $ipAddresses): Machine
    {
        return new Machine($id, $state, $stateCategory, $ipAddresses);
    }

    public static function createRandom(): Machine
    {
        return self::create(md5((string) rand()), md5((string) rand()), md5((string) rand()), []);
    }
}
