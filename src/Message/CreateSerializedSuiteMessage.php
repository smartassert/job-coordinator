<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\RemoteRequestType;

class CreateSerializedSuiteMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string                          $jobId
     * @param non-empty-string                          $suiteId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        string $jobId,
        public readonly string $suiteId,
        public readonly array $parameters,
    ) {
        parent::__construct($jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::createForSerializedSuiteCreation();
    }
}
