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

        self::assertEquals(
            [
                'id' => $id,
                'suite_id' => $suiteId,
            ],
            (new Job($id, $userId, $suiteId, $resultToken, $serializedSuiteId))->jsonSerialize()
        );
    }
}
