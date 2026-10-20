<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Machine;
use App\Model\JobComponent\ResultsJob;
use App\Model\JobComponent\SerializedSuite;
use App\Model\JobComponent\WorkerJob;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedPreparationState from PreparationState
 * @phpstan-import-type SerializedResultsJob from ResultsJob
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuite
 * @phpstan-import-type SerializedMachine from Machine
 * @phpstan-import-type SerializedWorkerState from WorkerJob
 *
 * @phpstan-type SerializedJobStatus array{
 *      id: non-empty-string,
 *      suite_id: non-empty-string,
 *      maximum_duration_in_seconds: positive-int,
 *      created_at: positive-int,
 *      meta_state: SerializedMetaState,
 *      preparation: SerializedPreparationState,
 *      components: array{
 *          results-job: SerializedResultsJob,
 *          serialized-suite: SerializedSerializedSuite,
 *          machine: SerializedMachine,
 *          worker-job: SerializedWorkerState,
 *     }
 * }
 */
readonly class JobStatus implements \JsonSerializable
{
    public function __construct(
        private JobInterface $job,
        private MetaState $metaState,
        private PreparationState $preparationState,
        private ResultsJob $resultsJob,
        private SerializedSuite $serializedSuite,
        private Machine $machine,
        private WorkerJob $workerJob,
    ) {}

    /**
     * @return array{
     *     id: non-empty-string,
     *     suite_id: non-empty-string,
     *     maximum_duration_in_seconds: positive-int,
     *     created_at: positive-int,
     *     meta_state: SerializedMetaState,
     *     preparation: SerializedPreparationState,
     *     components: array{
     *         results-job: SerializedResultsJob,
     *         serialized-suite: SerializedSerializedSuite,
     *         machine: SerializedMachine,
     *         worker-job: SerializedWorkerState,
     *     }
     * }
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->job->toArray(),
            [
                'meta_state' => $this->metaState->jsonSerialize(),
                'preparation' => $this->preparationState->jsonSerialize(),
                'components' => [
                    JobComponentName::RESULTS_JOB->value => $this->resultsJob->jsonSerialize(),
                    JobComponentName::SERIALIZED_SUITE->value => $this->serializedSuite->jsonSerialize(),
                    JobComponentName::MACHINE->value => $this->machine->jsonSerialize(),
                    JobComponentName::WORKER_JOB->value => $this->workerJob->jsonSerialize(),
                ],
            ]
        );
    }
}
