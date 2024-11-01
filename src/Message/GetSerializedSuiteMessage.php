<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class GetSerializedSuiteMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly string $serializedSuiteId,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::SERIALIZED_SUITE;
    }

    public function getRemoteRequestAction(): RemoteRequestAction
    {
        return RemoteRequestAction::RETRIEVE;
    }

    public function isRepeatable(): bool
    {
        return true;
    }
}
