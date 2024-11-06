<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\PreparationState;
use App\Enum\RemoteRequestEntity;
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
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::FAILED
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::FAILED
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::FAILED
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::FAILED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::SUCCEEDED
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::RESULTS_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::PENDING
                    ),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    RemoteRequestEntity::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestEntity::WORKER_JOB,
                        PreparationState::PENDING
                    ),
                    RemoteRequestEntity::MACHINE->value => new ComponentPreparation(
                        RemoteRequestEntity::MACHINE,
                        PreparationState::SUCCEEDED
                    ),
                    RemoteRequestEntity::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestEntity::SERIALIZED_SUITE,
                        PreparationState::PREPARING
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
