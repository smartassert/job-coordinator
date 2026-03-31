<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\RemoteRequestFailure;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;

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
        private ComponentPreparation $componentPreparation,
        private RequestState $requestState,
    ) {}

    /**
     * @return SerializedPreparation
     */
    public function jsonSerialize(): array
    {
        $data = [
            'state' => $this->componentPreparation->state->value,
            'request_state' => $this->requestState->value,
        ];

        if ($this->componentPreparation->failure instanceof RemoteRequestFailure) {
            $data['failure'] = $this->componentPreparation->failure->jsonSerialize();
        }

        return $data;
    }
}
