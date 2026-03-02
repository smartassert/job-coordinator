<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\JobComponentName;

/**
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 */
readonly class JobStatus implements \JsonSerializable
{
    public function __construct(
        private JobInterface $job,
        private MetaState $metaState,
        private PreparationState $preparationState,
        private ?ResultsJob $resultsJob,
        private ?SerializedSuite $serializedSuite,
        private ?Machine $machine,
        private WorkerState $workerState,
        private RemoteRequestCollection $serviceRequests,
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
                JobComponentName::RESULTS_JOB->value => $this->resultsJob,
                JobComponentName::SERIALIZED_SUITE->value => $this->serializedSuite,
                JobComponentName::MACHINE->value => $this->machine,
                JobComponentName::WORKER_JOB->value => $this->workerState,
                'service_requests' => $this->serviceRequests,
            ]
        );
    }
}
