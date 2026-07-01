<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Tests\Services\Generator\StringValue;
use SmartAssert\WorkerClient\Model\Job;
use SmartAssert\WorkerClient\Model\ResourceReference;

class WorkerClientJobFactory
{
    public static function createRandom(): Job
    {
        return new Job(
            new ResourceReference(StringValue::random(), StringValue::random()),
            rand(1, 1000),
            [],
            [],
            [],
            [],
            [],
        );
    }
}
