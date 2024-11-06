<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponent;
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
        self::assertSame($expected, (new PreparationStateReducer())->reduce($componentPreparationStates));
    }

    /**
     * @return array<mixed>
     */
    public static function reduceDataProvider(): array
    {
        return [
            'any occurrence of "failed" is "failed" (1)' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::FAILED
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::FAILED
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::FAILED
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::FAILED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    JobComponent::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponent::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::PENDING
                    ),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    JobComponent::WORKER_JOB->value => new ComponentPreparation(
                        JobComponent::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponent::MACHINE->value => new ComponentPreparation(
                        JobComponent::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponent::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponent::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
