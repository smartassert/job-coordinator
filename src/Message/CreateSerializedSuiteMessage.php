<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class CreateSerializedSuiteMessage implements JobMessageInterface, RemoteRequestMessageInterface
{
    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly array $parameters,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::SERIALIZED_SUITE_CREATE;
    }
}
