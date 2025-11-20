<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;

class ResultsJobHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        ResultsJobRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function handles(JobComponent $jobComponent): bool
    {
        return JobComponent::RESULTS_JOB === $jobComponent;
    }

    public function getComponentPreparation(string $jobId): ?ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, JobComponent::RESULTS_JOB);
    }

    public function getRequestState(string $jobId): ?RequestState
    {
        return $this->doGetRequestState(
            $jobId,
            new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::CREATE)
        );
    }

    protected function getJobComponent(): JobComponent
    {
        return JobComponent::RESULTS_JOB;
    }
}
