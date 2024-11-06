<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;

class SerializedSuiteHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        SerializedSuiteRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    protected function getJobComponent(): JobComponent
    {
        return JobComponent::SERIALIZED_SUITE;
    }
}
