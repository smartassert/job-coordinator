<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Job;
use Monolog\Test\TestCase;
use Symfony\Component\Uid\Ulid;

class JobTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $userId = (string) new Ulid();
        \assert('' !== $userId);

        $suiteId = (string) new Ulid();
        \assert('' !== $suiteId);

        $label = (string) new Ulid();
        \assert('' !== $label);

        self::assertEquals(
            [
                'suite_id' => $suiteId,
                'label' => $label,
            ],
            (new Job($userId, $suiteId, $label))->jsonSerialize()
        );
    }
}
