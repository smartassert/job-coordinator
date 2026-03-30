<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\JobComponentName;
use App\Enum\RemoteRequestAction;

readonly class RemoteRequestType implements \Stringable
{
    private function __construct(
        public JobComponentName $componentName,
        public RemoteRequestAction $action,
    ) {}

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->componentName->value . '/' . $this->action->value;
    }

    public function equals(RemoteRequestType $type): bool
    {
        return $this->componentName === $type->componentName && $this->action === $type->action;
    }

    public static function createForMachineCreation(): self
    {
        return new RemoteRequestType(JobComponentName::MACHINE, RemoteRequestAction::CREATE);
    }

    public static function createForResultsJobCreation(): self
    {
        return new RemoteRequestType(JobComponentName::RESULTS_JOB, RemoteRequestAction::CREATE);
    }

    public static function createForSerializedSuiteCreation(): self
    {
        return new RemoteRequestType(JobComponentName::SERIALIZED_SUITE, RemoteRequestAction::CREATE);
    }

    public static function createForWorkerJobCreation(): self
    {
        return new RemoteRequestType(JobComponentName::WORKER_JOB, RemoteRequestAction::CREATE);
    }

    public static function createForMachineRetrieval(): self
    {
        return new RemoteRequestType(JobComponentName::MACHINE, RemoteRequestAction::RETRIEVE);
    }

    public static function createForResultsJobRetrieval(): self
    {
        return new RemoteRequestType(JobComponentName::RESULTS_JOB, RemoteRequestAction::RETRIEVE);
    }

    public static function createForSerializedSuiteRetrieval(): self
    {
        return new RemoteRequestType(JobComponentName::SERIALIZED_SUITE, RemoteRequestAction::RETRIEVE);
    }

    public static function createForWorkerJobRetrieval(): self
    {
        return new RemoteRequestType(JobComponentName::WORKER_JOB, RemoteRequestAction::RETRIEVE);
    }

    public static function createForMachineTermination(): self
    {
        return new RemoteRequestType(JobComponentName::MACHINE, RemoteRequestAction::TERMINATE);
    }

    /**
     * @return RemoteRequestType[]
     */
    public static function getAllForComponent(JobComponentName $componentName): array
    {
        $types = [];

        foreach (RemoteRequestAction::cases() as $action) {
            $types[] = new RemoteRequestType($componentName, $action);
        }

        return $types;
    }
}
