<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequest;

/**
 * @phpstan-import-type SerializedRemoteRequestTypeAttempts from RemoteRequestTypeAttempts
 *
 * @phpstan-type SerializedRemoteRequestCollection array<SerializedRemoteRequestTypeAttempts>
 */
class RemoteRequestCollection
{
    /**
     * @param iterable<RemoteRequest> $requests
     */
    public function __construct(
        private readonly iterable $requests,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $requestsByType = [];
        foreach ($this->requests as $request) {
            if (!isset($requestsByType[$request->getType()->value])) {
                $requestsByType[$request->getType()->value] = [];
            }

            $requestsByType[$request->getType()->value][] = $request;
        }

        $data = [];
        foreach ($requestsByType as $typedRequests) {
            $firstRequest = $typedRequests[0] ?? null;
            if ($firstRequest instanceof RemoteRequest) {
                $typeAttempts = new RemoteRequestTypeAttempts($firstRequest->getType(), $typedRequests);

                $data[] = $typeAttempts->toArray();
            }
        }

        return $data;
    }
}
