<?php

declare(strict_types=1);

namespace App\Model;

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
                    $this->resultsJob->getName()->value => $this->resultsJob->jsonSerialize(),
                    $this->serializedSuite->getName()->value => $this->serializedSuite->jsonSerialize(),
                    $this->machine->getName()->value => $this->machine->jsonSerialize(),
                    $this->workerJob->getName()->value => $this->workerJob->jsonSerialize(),
                ],
            ]
        );
    }
}
