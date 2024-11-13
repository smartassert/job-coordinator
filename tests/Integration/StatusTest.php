<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Application\AbstractStatusTest;

class StatusTest extends AbstractStatusTest
{
    use GetClientAdapterTrait;

    protected function getExpectedReadyValue(): bool
    {
        return false;
    }

    protected function getExpectedVersion(): string
    {
        $expectedVersion = $_SERVER['EXPECTED_VERSION'] ?? null;

        return is_string($expectedVersion) ? $expectedVersion : 'docker_compose_version';
    }
}
