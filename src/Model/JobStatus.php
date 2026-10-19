<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Machine;
use App\Model\JobComponent\ResultsJob;
use App\Model\JobComponent\SerializedSuite;
use App\Model\JobComponent\WorkerJob;

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
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->job->toArray(),
            [
                'meta_state' => $this->metaState,
                'preparation' => $this->preparationState,
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
