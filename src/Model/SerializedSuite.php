<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedRemoteRequest from SerializableRemoteRequestInterface
 *
 * @phpstan-type SerializedSerializedSuite array{request: SerializedRemoteRequest, state: ?non-empty-string}
 */
class SerializedSuite
{
    public function __construct(
        private readonly SerializedSuiteInterface $entity,
        private readonly SerializableRemoteRequestInterface $request,
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
