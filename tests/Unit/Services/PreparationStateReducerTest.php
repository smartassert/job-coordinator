<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\PreparationState;
use App\Services\PreparationStateReducer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PreparationStateReducerTest extends TestCase
{
    /**
     * @param PreparationState[] $preparationStates
     */
    #[DataProvider('reduceDataProvider')]
    public function testReduce(array $preparationStates, PreparationState $expected): void
    {
        self::assertSame($expected, new PreparationStateReducer()->reduce($preparationStates));
    }

    /**
     * @return array<mixed>
     */
    public static function reduceDataProvider(): array
    {
        return [
            'empty' => [
                'preparationStates' => [],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "failed" is "failed"' => [
                'preparationStates' => [
                    PreparationState::FAILED,
                    PreparationState::PENDING,
                    PreparationState::PREPARING,
                    PreparationState::SUCCEEDED,
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'preparationStates' => [
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                    PreparationState::SUCCEEDED,
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'preparationStates' => [
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                    PreparationState::PENDING,
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing"' => [
                'preparationStates' => [
                    PreparationState::PREPARING,
                    PreparationState::PENDING,
                    PreparationState::SUCCEEDED,
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
