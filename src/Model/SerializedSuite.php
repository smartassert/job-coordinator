<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 *
 * @phpstan-type SerializedSerializedSuite array{request: SerializedRemoteRequest, state: ?non-empty-string}
 */
class SerializedSuite
{
    public function __construct(
        private readonly SerializedSuiteInterface $entity,
        private readonly RemoteRequestInterface $request,
    ) {
    }

    /**
     * @return SerializedSerializedSuite
     */
    public function toArray(): array
    {
        return [
            'request' => $this->request->toArray(),
            'state' => $this->entity->getState(),
        ];
    }
}
