<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerJobCreationFailureRepository;

class StagingConfiguration
{
    /**
     * @var \Closure(string, RemoteRequestRepository): void
     */
    private \Closure $remoteRequestsCreator;

    /**
     * @var \Closure(string, SerializedSuiteRepository): SerializedSuite
     */
    private \Closure $serializedSuiteCreator;

    /**
     * @var \Closure(string, MachineRepository): Machine
     */
    private \Closure $machineCreator;

    /**
     * @var \Closure(string, ResultsJobRepository): ResultsJob
     */
    private \Closure $resultsJobCreator;

    /**
     * @var \Closure(string, WorkerComponentStateRepository): void
     */
    private \Closure $workerComponentStatesCreator;

    /**
     * @var \Closure(string, WorkerJobCreationFailureRepository): void
     */
    private \Closure $workerJobCreationFailureCreator;

    /**
     * @return \Closure(string, RemoteRequestRepository): void
     */
    public function getRemoteRequestsCreator(): \Closure
    {
        if (!isset($this->remoteRequestsCreator)) {
            return function () {};
        }

        return $this->remoteRequestsCreator;
    }

    /**
     * @param \Closure(string, RemoteRequestRepository): void $remoteRequestsCreator
     */
    public function withRemoteRequestsCreator(\Closure $remoteRequestsCreator): self
    {
        $new = clone $this;
        $new->remoteRequestsCreator = $remoteRequestsCreator;

        return $new;
    }

    /**
     * @return \Closure(string, SerializedSuiteRepository): SerializedSuite
     */
    public function getSerializedSuiteCreator(): \Closure
    {
        if (!isset($this->serializedSuiteCreator)) {
            return function () {
                return \Mockery::mock(SerializedSuite::class);
            };
        }

        return $this->serializedSuiteCreator;
    }

    /**
     * @param \Closure(string, SerializedSuiteRepository): SerializedSuite $serializedSuiteCreator
     */
    public function withSerializedSuiteCreator(\Closure $serializedSuiteCreator): self
    {
        $new = clone $this;
        $new->serializedSuiteCreator = $serializedSuiteCreator;

        return $new;
    }

    /**
     * @return \Closure(string, MachineRepository): Machine
     */
    public function getMachineCreator(): \Closure
    {
        if (!isset($this->machineCreator)) {
            return function () {
                return \Mockery::mock(Machine::class);
            };
        }

        return $this->machineCreator;
    }

    /**
     * @param \Closure(string, MachineRepository): Machine $machineCreator
     */
    public function withMachineCreator(\Closure $machineCreator): self
    {
        $new = clone $this;
        $new->machineCreator = $machineCreator;

        return $new;
    }

    /**
     * @return \Closure(string, ResultsJobRepository): ResultsJob
     */
    public function getResultsJobCreator(): \Closure
    {
        if (!isset($this->resultsJobCreator)) {
            return function () {
                return \Mockery::mock(ResultsJob::class);
            };
        }

        return $this->resultsJobCreator;
    }

    /**
     * @param \Closure(string, ResultsJobRepository): ResultsJob $resultsJobCreator
     */
    public function withResultsJobCreator(\Closure $resultsJobCreator): self
    {
        $new = clone $this;
        $new->resultsJobCreator = $resultsJobCreator;

        return $new;
    }

    /**
     * @return \Closure(string, WorkerComponentStateRepository): void
     */
    public function getWorkerComponentStatesCreator(): \Closure
    {
        if (!isset($this->workerComponentStatesCreator)) {
            return function () {};
        }

        return $this->workerComponentStatesCreator;
    }

    /**
     * @param \Closure(string, WorkerComponentStateRepository): void $workerComponentStatesCreator
     */
    public function withWorkerComponentStatesCreator(\Closure $workerComponentStatesCreator): self
    {
        $new = clone $this;
        $new->workerComponentStatesCreator = $workerComponentStatesCreator;

        return $new;
    }

    /**
     * @return \Closure(string, WorkerJobCreationFailureRepository): void
     */
    public function getWorkerJobCreationFailureCreator(): \Closure
    {
        if (!isset($this->workerJobCreationFailureCreator)) {
            return function () {};
        }

        return $this->workerJobCreationFailureCreator;
    }

    /**
     * @param \Closure(string, WorkerJobCreationFailureRepository): void $workerJobCreationFailureCreator
     */
    public function withWorkerJobCreationFailureCreator(\Closure $workerJobCreationFailureCreator): self
    {
        $new = clone $this;
        $new->workerJobCreationFailureCreator = $workerJobCreationFailureCreator;

        return $new;
    }
}
