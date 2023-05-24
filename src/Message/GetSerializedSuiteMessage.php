<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class GetSerializedSuiteMessage implements JobRemoteRequestMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $serializedSuiteId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::SERIALIZED_SUITE_GET;
    }
}
