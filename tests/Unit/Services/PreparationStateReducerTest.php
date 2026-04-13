<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\PreparationState;
use App\Model\ComponentPreparation;
use App\Services\PreparationStateReducer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PreparationStateReducerTest extends TestCase
{
    /**
     * @param ComponentPreparation[] $componentPreparationStates
     */
    #[DataProvider('reduceDataProvider')]
    public function testReduce(array $componentPreparationStates, PreparationState $expected): void
    {
        self::assertSame($expected, new PreparationStateReducer()->reduce($componentPreparationStates));
    }

    /**
     * @return array<mixed>
     */
    public static function reduceDataProvider(): array
    {
        return [
            'any occurrence of "failed" is "failed"' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::FAILED),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PENDING),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing"' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
