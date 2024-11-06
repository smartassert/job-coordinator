<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Model\RemoteRequestType;
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
        $resultsComponent = new JobComponent(
            JobComponentName::RESULTS_JOB,
            new RemoteRequestType(RemoteRequestEntity::RESULTS_JOB, RemoteRequestAction::CREATE),
            RemoteRequestEntity::RESULTS_JOB,
        );

        $serializedSuiteComponent = new JobComponent(
            JobComponentName::SERIALIZED_SUITE,
            new RemoteRequestType(RemoteRequestEntity::SERIALIZED_SUITE, RemoteRequestAction::CREATE),
            RemoteRequestEntity::SERIALIZED_SUITE,
        );

        $machineComponent = new JobComponent(
            JobComponentName::MACHINE,
            new RemoteRequestType(RemoteRequestEntity::MACHINE, RemoteRequestAction::CREATE),
            RemoteRequestEntity::MACHINE,
        );

        $workerComponent = new JobComponent(
            JobComponentName::WORKER_JOB,
            new RemoteRequestType(RemoteRequestEntity::WORKER_JOB, RemoteRequestAction::CREATE),
            RemoteRequestEntity::WORKER_JOB,
        );

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
