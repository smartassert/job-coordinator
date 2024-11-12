<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 *
 * @phpstan-type SerializedRemoteRequestCollection array<
 *   array{
 *     type: non-empty-string,
 *     attempts: array<SerializedRemoteRequest>
 *   }
 * >
 */
class RemoteRequestCollection implements \JsonSerializable
{
    /**
     * @param iterable<RemoteRequestInterface&TypedRemoteRequestInterface> $requests
     */
    public function __construct(
        private readonly iterable $requests,
    ) {
    }

    /**
     * @return SerializedRemoteRequestCollection
     */
    public function jsonSerialize(): array
    {
        $requestsByType = [];
        foreach ($this->requests as $request) {
            $requestType = $request->getType();
            if (null !== $requestType) {
                if (!isset($requestsByType[$requestType])) {
                    $requestsByType[$requestType] = [];
                }

                $requestsByType[$requestType][] = $request->toArray();
            }
        }

        $data = [];
        foreach ($requestsByType as $type => $requestGroup) {
            $groupData = [
                'type' => $type,
                'attempts' => $requestGroup,
            ];

            $data[] = $groupData;
        }

        return $data;
    }
}
