<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Model\JobInterface;
use App\Services\JobStore;
use App\Tests\Services\Generator\Id;

readonly class JobFactory
{
    public function __construct(
        private JobStore $jobStore,
    ) {}

    public function createRandom(): JobInterface
    {
        $userId = Id::generate();
        $suiteId = Id::generate();

        $maximumDurationInSeconds = rand(1, 1000);

        return $this->jobStore->create($userId, $suiteId, $maximumDurationInSeconds);
    }
}
