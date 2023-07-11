<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\RemoteRequestType;
use PHPUnit\Framework\TestCase;

class RemoteRequestTypeTest extends TestCase
{
    /**
     * @dataProvider isRepeatableDataProvider
     */
    public function testIsRepeatable(RemoteRequestType $type, bool $expected): void
    {
        self::assertSame($expected, $type->isRepeatable());
    }

    /**
     * @return array<mixed>
     */
    public function isRepeatableDataProvider(): array
    {
        return [
            RemoteRequestType::MACHINE_CREATE->value => [
                'type' => RemoteRequestType::MACHINE_CREATE,
                'expected' => false,
            ],
            RemoteRequestType::MACHINE_GET->value => [
                'type' => RemoteRequestType::MACHINE_GET,
                'expected' => true,
            ],
            RemoteRequestType::MACHINE_START_JOB->value => [
                'type' => RemoteRequestType::MACHINE_START_JOB,
                'expected' => false,
            ],
            RemoteRequestType::RESULTS_CREATE->value => [
                'type' => RemoteRequestType::RESULTS_CREATE,
                'expected' => false,
            ],
            RemoteRequestType::SERIALIZED_SUITE_CREATE->value => [
                'type' => RemoteRequestType::SERIALIZED_SUITE_CREATE,
                'expected' => false,
            ],
            RemoteRequestType::SERIALIZED_SUITE_READ->value => [
                'type' => RemoteRequestType::SERIALIZED_SUITE_READ,
                'expected' => true,
            ],
            RemoteRequestType::SERIALIZED_SUITE_GET->value => [
                'type' => RemoteRequestType::SERIALIZED_SUITE_GET,
                'expected' => true,
            ],
            RemoteRequestType::RESULTS_STATE_GET->value => [
                'type' => RemoteRequestType::RESULTS_STATE_GET,
                'expected' => true,
            ],
            RemoteRequestType::MACHINE_TERMINATE->value => [
                'type' => RemoteRequestType::MACHINE_TERMINATE,
                'expected' => false,
            ],
            RemoteRequestType::MACHINE_STATE_GET->value => [
                'type' => RemoteRequestType::MACHINE_STATE_GET,
                'expected' => true,
            ],
        ];
    }
}
