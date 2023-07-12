<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\PreparationState;
use App\Model\ComponentPreparation;
use App\Services\PreparationStateReducer;
use PHPUnit\Framework\TestCase;

class PreparationStateReducerTest extends TestCase
{
    /**
     * @dataProvider reduceDataProvider
     *
     * @param ComponentPreparation[] $componentPreparationStates
     */
    public function testReduce(array $componentPreparationStates, PreparationState $expected): void
    {
        self::assertSame($expected, (new PreparationStateReducer())->reduce($componentPreparationStates));
    }

    /**
     * @return array<mixed>
     */
    public function reduceDataProvider(): array
    {
        return [
            'any occurrence of "failed" is "failed" (1)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::FAILED),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::FAILED),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::FAILED),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                    new ComponentPreparation(PreparationState::FAILED),
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
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::PREPARING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    new ComponentPreparation(PreparationState::PENDING),
                    new ComponentPreparation(PreparationState::SUCCEEDED),
                    new ComponentPreparation(PreparationState::PREPARING),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
