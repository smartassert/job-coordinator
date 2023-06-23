<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequest
 *
 * @phpstan-type SerializedRemoteRequestTypeAttempts array{
 *  type: value-of<RemoteRequestType>,
 *  attempts: array<SerializedRemoteRequest>
 * }
 */
class RemoteRequestTypeAttempts
{
    /**
     * @param iterable<RemoteRequest> $requests
     */
    public function __construct(
        private readonly RemoteRequestType $type,
        private readonly iterable $requests,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $requests = [];
        foreach ($this->requests as $request) {
            $requests[] = $request->toArray();
        }

        return [
            'type' => $this->type->value,
            'attempts' => $requests,
        ];
    }
}
