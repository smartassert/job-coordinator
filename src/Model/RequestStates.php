<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RequestState;

/**
 * @phpstan-type SerializedRequestStates array<non-empty-string, value-of<RequestState>>
 */
class RequestStates
{
    /**
     * @param array<non-empty-string, RequestState> $requestStates
     */
    public function __construct(
        private readonly array $requestStates,
    ) {
    }

    /**
     * @return SerializedRequestStates
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->requestStates as $name => $requestState) {
            $data[$name] = $requestState->value;
        }

        return $data;
    }
}
