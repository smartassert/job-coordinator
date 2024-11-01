<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class CreateSerializedSuiteMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly array $parameters,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::SERIALIZED_SUITE;
    }

    public function getRemoteRequestAction(): RemoteRequestAction
    {
        return RemoteRequestAction::CREATE;
    }

    public function isRepeatable(): bool
    {
        return false;
    }
}
