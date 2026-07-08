<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\RemoteRequestType;

class GetSerializedSuiteMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        string $jobId,
        public readonly string $suiteId,
        public readonly string $serializedSuiteId,
    ) {
        parent::__construct($jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::createForSerializedSuiteRetrieval();
    }
}
