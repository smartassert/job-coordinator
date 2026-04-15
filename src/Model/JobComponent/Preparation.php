<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\RemoteRequestFailure;
use App\Enum\PreparationState;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;

/**
 * @phpstan-import-type SerializedRemoteRequestFailure from RemoteRequestFailure
 *
 * @phpstan-type SerializedPreparation array{
 *   state: value-of<PreparationStateEnum>,
 *   request_state: value-of<RequestState>,
 *   failure?: SerializedRemoteRequestFailure
 * }
 */
readonly class Preparation implements \JsonSerializable
{
    public function __construct(
        private PreparationState $preparationState,
        private RequestState $requestState,
        private ?RemoteRequestFailure $remoteRequestFailure = null,
    ) {}

    public function hasFailure(): bool
    {
        return PreparationState::FAILED === $this->preparationState;
    }

    /**
     * @return SerializedPreparation
     */
    public function jsonSerialize(): array
    {
        $data = [
            'state' => $this->preparationState->value,
            'request_state' => $this->requestState->value,
        ];

        if ($this->remoteRequestFailure instanceof RemoteRequestFailure) {
            $data['failure'] = $this->remoteRequestFailure->jsonSerialize();
        }

        return $data;
    }
}
