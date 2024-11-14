<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Model\RemoteRequestType;

class CreateSerializedSuiteMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param non-empty-string                          $suiteId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly string $suiteId,
        public readonly array $parameters,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(JobComponent::SERIALIZED_SUITE, RemoteRequestAction::CREATE);
    }
}
