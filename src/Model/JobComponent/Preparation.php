<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\RemoteRequestFailure;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailure
 */
readonly class Preparation implements \JsonSerializable
{
    public function __construct(
        private PreparationStateEnum $state,
        private RequestState $requestState,
        private ?RemoteRequestFailure $failure,
    ) {}

    /**
     * @return array{
     *     state: value-of<PreparationStateEnum>,
     *     request_state: value-of<RequestState>,
     *     failure?: SerializedRemoteRequestFailure
     * }
     */
    public function jsonSerialize(): array
    {
        $data = [
            'state' => $this->state->value,
            'request_state' => $this->requestState->value,
        ];

        if ($this->failure instanceof RemoteRequestFailure) {
            $data['failure'] = $this->failure->jsonSerialize();
        }

        return $data;
    }
}
