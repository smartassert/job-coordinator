<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
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
        $resultsComponent = new JobComponent(JobComponentName::RESULTS_JOB, RemoteRequestType::RESULTS_CREATE);
        $serializedSuiteComponent = new JobComponent(
            JobComponentName::SERIALIZED_SUITE,
            RemoteRequestType::SERIALIZED_SUITE_CREATE
        );
        $machineComponent = new JobComponent(JobComponentName::MACHINE, RemoteRequestType::MACHINE_CREATE);
        $workerComponent = new JobComponent(JobComponentName::WORKER_JOB, RemoteRequestType::MACHINE_START_JOB);

        return [
            'any occurrence of "failed" is "failed" (1)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::FAILED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::FAILED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::FAILED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'any occurrence of "failed" is "failed" (4)' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::FAILED
                    ),
                ],
                'expected' => PreparationState::FAILED,
            ],
            'all "succeeded" is "succeeded"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::SUCCEEDED,
            ],
            'all "pending" is "pending"' => [
                'componentPreparationStates' => [
                    JobComponentName::RESULTS_JOB->value => new ComponentPreparation(
                        $resultsComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::PENDING
                    ),
                ],
                'expected' => PreparationState::PENDING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (1)' => [
                'componentPreparationStates' => [
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (2)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::SUCCEEDED
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
            'any occurrence of "preparing" without any "failure" is "preparing" (3)' => [
                'componentPreparationStates' => [
                    JobComponentName::WORKER_JOB->value => new ComponentPreparation(
                        $workerComponent,
                        PreparationState::PENDING
                    ),
                    JobComponentName::MACHINE->value => new ComponentPreparation(
                        $machineComponent,
                        PreparationState::SUCCEEDED
                    ),
                    JobComponentName::SERIALIZED_SUITE->value => new ComponentPreparation(
                        $serializedSuiteComponent,
                        PreparationState::PREPARING
                    ),
                ],
                'expected' => PreparationState::PREPARING,
            ],
        ];
    }
}
