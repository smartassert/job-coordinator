<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;

readonly class RemoteRequestType implements \Stringable
{
    public function __construct(
        public JobComponent $jobComponent,
        public RemoteRequestAction $action,
    ) {}

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->jobComponent->value . '/' . $this->action->value;
    }

    public static function createForMachineCreation(): self
    {
        return new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE);
    }

    public static function createForResultsJobCreation(): self
    {
        return new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::CREATE);
    }

    public static function createForSerializedSuiteCreation(): self
    {
        return new RemoteRequestType(JobComponent::SERIALIZED_SUITE, RemoteRequestAction::CREATE);
    }

    public static function createForWorkerJobCreation(): self
    {
        return new RemoteRequestType(JobComponent::WORKER_JOB, RemoteRequestAction::CREATE);
    }

    public static function createForMachineRetrieval(): self
    {
        return new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::RETRIEVE);
    }

    public static function createForResultsJobRetrieval(): self
    {
        return new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::RETRIEVE);
    }
}
