<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Model\Machine\Machine;
use App\Model\Machine\MachineInterface;
use App\Model\MetaState;
use App\Tests\Services\Generator\BoolValue;
use App\Tests\Services\Generator\StringValue;

class MachineFactory
{
    /**
     * @param non-empty-string $jobId
     */
    public static function createRandomForJob(string $jobId): MachineInterface
    {
        $failed = BoolValue::random();
        $active = BoolValue::random();
        $ending = BoolValue::random();
        $ready = BoolValue::random();
        $ended = BoolValue::random();
        $succeeded = $ended && !$failed;
        $pending = !$ended && !$active;

        return new Machine(
            $jobId,
            StringValue::random(),
            StringValue::random(),
            null,
            null,
            new MetaState($ended, $succeeded, $pending),
            $active,
            $ready,
            $ending,
        );
    }
}
