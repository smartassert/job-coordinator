<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponentName;
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
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::FAILED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::FAILED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::FAILED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::FAILED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        JobComponentName::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::PENDING
                    ),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        JobComponentName::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        JobComponentName::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        JobComponentName::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
