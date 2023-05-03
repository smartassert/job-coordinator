<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Job;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class JobTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $id = (string) new Ulid();
        \assert('' !== $id);

        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $resultToken = (string) new Ulid();
        \assert('' !== $resultToken);

        $serializedSuiteId = (string) new Ulid();
        \assert('' !== $serializedSuiteId);

        $maximumDurationInSeconds = rand(1, 1000);

        $job = (new Job($id, $userId, $suiteId, $resultToken, $serializedSuiteId, $maximumDurationInSeconds));

        self::assertEquals(
            [
                'id' => $id,
                'suite_id' => $suiteId,
                'maximum_duration_in_seconds' => $maximumDurationInSeconds,
                'serialized_suite' => [
                    'id' => $serializedSuiteId,
                    'state' => null,
                ],
                'machine' => [
                    'state_category' => null,
                    'ip_address' => null,
                ],
            ],
            $job->jsonSerialize()
        );
    }
}
