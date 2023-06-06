<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequest;

class RemoteRequestCollection implements \JsonSerializable
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
    public function jsonSerialize(): array
    {
        $requestsByType = [];
        foreach ($this->requests as $request) {
            if (!isset($requestsByType[$request->getType()->value])) {
                $requestsByType[$request->getType()->value] = [];
            }

            $requestsByType[$request->getType()->value][] = $request;
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