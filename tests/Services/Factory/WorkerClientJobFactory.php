<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use SmartAssert\WorkerClient\Model\Job;
use SmartAssert\WorkerClient\Model\ResourceReference;

class WorkerClientJobFactory
{
    public static function createRandom(): Job
    {
        return new Job(
            new ResourceReference(md5((string) rand()), md5((string) rand())),
            rand(1, 1000),
            [],
            [],
            [],
            [],
            [],
        );
    }
}
