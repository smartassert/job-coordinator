<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\RemoteRequestFailure;
use App\Enum\RequestState;

/**
 * @phpstan-import-type SerializedRemoteRequest from RemoteRequestInterface
 */
class RemoteRequest implements RemoteRequestInterface
{
    public function __construct(
        private readonly RequestState $state,
        private readonly ?RemoteRequestFailure $failure = null,
    ) {
    }

    /**
     * @return SerializedRemoteRequest
     */
    public function toArray(): array
    {
        $data = [
            'state' => $this->state->value,
        ];

        if ($this->failure instanceof RemoteRequestFailure) {
            $data['failure'] = $this->failure->toArray();
        }

        return $data;
    }
}
