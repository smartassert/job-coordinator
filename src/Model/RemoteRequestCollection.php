<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedRemoteRequestCollection array<
 *   array{
 *     type: non-empty-string,
 *     attempts: RemoteRequestInterface[]
 *   }
 * >
 */
class RemoteRequestCollection implements \JsonSerializable
{
    /**
     * @param array<RemoteRequestInterface&TypedRemoteRequestInterface> $requests
     */
    public function __construct(
        private readonly array $requests,
    ) {}

    public function isEmpty(): bool
    {
        return 0 === count($this->requests);
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

                $requestsByType[$requestType][] = $request;
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
