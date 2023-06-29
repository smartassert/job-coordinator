<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 *
 * @phpstan-type SerializedResultsJob array{
 *   request: SerializedRemoteRequest,
 *   state: ?non-empty-string,
 *   end_state: ?non-empty-string
 * }
 */
class ResultsJob
{
    public function __construct(
        private readonly ResultsJobInterface $entity,
        private readonly RemoteRequestInterface $request,
    ) {
    }

    /**
     * @return SerializedResultsJob
     */
    public function toArray(): array
    {
        return [
            'request' => $this->request->toArray(),
            'state' => $this->entity->getState(),
            'end_state' => $this->entity->getEndState(),
        ];
    }
}
