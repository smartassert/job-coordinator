<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Job;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class JobTest extends TestCase
{
    public function testToArray(): void
    {
        $id = (string) new Ulid();
        \assert('' !== $id);

        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $resultToken = (string) new Ulid();
        \assert('' !== $resultToken);

        $maximumDurationInSeconds = rand(1, 1000);

        $job = (new Job($id, $userId, $suiteId, $maximumDurationInSeconds));

        self::assertEquals(
            [
                'id' => $id,
                'suite_id' => $suiteId,
                'maximum_duration_in_seconds' => $maximumDurationInSeconds,
                'serialized_suite' => [
                    'id' => null,
                    'state' => null,
                ],
                'machine' => [
                    'state_category' => null,
                    'ip_address' => null,
                ],
                'results_job' => [
                    'has_token' => false,
                    'state' => null,
                    'end_state' => null,
                ],
            ],
            $job->toArray()
        );
    }
}
