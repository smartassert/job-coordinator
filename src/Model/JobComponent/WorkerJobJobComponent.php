<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\WorkerJobCreationFailure;
use App\Enum\JobComponentName;
use App\Model\FailedWorkerComponentState;
use App\Model\WorkerState;

readonly class WorkerJobJobComponent implements NamedJobComponentInterface
{
    public function __construct(
        private WorkerJob $component,
        private ?WorkerJobCreationFailure $failure,
    ) {}

    public function getName(): JobComponentName
    {
        return JobComponentName::WORKER_JOB;
    }

    public function jsonSerialize(): ?array
    {
        if (null === $this->failure) {
            return $this->component->jsonSerialize();
        }

        $component = $this->component->withApplicationState(new FailedWorkerComponentState());

        return array_merge(
            $component->jsonSerialize(),
            [
                'creation_failure' => $this->failure->jsonSerialize(),
            ]
        );
    }
}
