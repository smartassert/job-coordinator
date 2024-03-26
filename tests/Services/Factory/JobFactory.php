<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\Job;
use Symfony\Component\Uid\Ulid;

readonly class JobFactory
{
    public static function createRandom(): Job
    {
        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $maximumDurationInSeconds = rand(1, 1000);
        $createdAt = new \DateTimeImmutable();

        return new Job($userId, $suiteId, $maximumDurationInSeconds, $createdAt);
    }
}
