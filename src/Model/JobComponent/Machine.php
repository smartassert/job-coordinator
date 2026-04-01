<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\Machine as MachineEntity;
use App\Entity\MachineActionFailure;
use App\Enum\JobComponentName;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 *
 * @phpstan-type SerializedMachine array{
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string,
 *   action_failure: ?MachineActionFailure,
 *   meta_state: MetaState,
 *   requests: SerializedRemoteRequestCollection,
 *   preparation: array{}
 * }
 */
readonly class Machine implements SerializeToArrayInterface, NamedJobComponentInterface
{
    public function __construct(
        private ?MachineEntity $entity,
        private RemoteRequestCollection $requests,
    ) {}

    public function getName(): JobComponentName
    {
        return JobComponentName::MACHINE;
    }

    public function getMetaState(): MetaState
    {
        return $this->entity?->getMetaState() ?? new MetaState(false, false);
    }

    /**
     * @return SerializedMachine
     */
    public function jsonSerialize(): array
    {
        return [
            'state_category' => $this->entity?->getStateCategory() ?? null,
            'ip_address' => $this->entity?->getIp() ?? null,
            'action_failure' => $this->entity?->getActionFailure() ?? null,
            'meta_state' => $this->getMetaState(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => [],
        ];
    }
}
