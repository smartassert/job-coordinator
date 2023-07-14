<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
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
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::FAILED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::FAILED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::FAILED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::FAILED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        RemoteRequestType::RESULTS_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::PENDING
                    ),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_START_JOB,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        RemoteRequestType::MACHINE_CREATE,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        RemoteRequestType::SERIALIZED_SUITE_CREATE,
                        PreparationState::PREPARING
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
