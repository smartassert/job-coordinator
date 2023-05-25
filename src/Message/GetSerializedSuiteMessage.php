<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class GetSerializedSuiteMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param int<0, max>      $index
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        int $index,
        public readonly string $serializedSuiteId,
    ) {
        parent::__construct($authenticationToken, $jobId, $index);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::SERIALIZED_SUITE_GET;
    }
}
