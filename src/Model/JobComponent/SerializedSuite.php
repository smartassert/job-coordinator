<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\SerializedSuite as SerializedSuiteEntity;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedRemoteRequestCollectionGroup from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 *
 * @phpstan-type SerializedSerializedSuite array{
 *   state: ?string,
 *   is_prepared: bool,
 *   meta_state: SerializedMetaState,
 *   requests: SerializedRemoteRequestCollectionGroup,
 *   preparation: SerializedPreparation
 * }
 */
readonly class SerializedSuite implements SerializeToArrayInterface
{
    public function __construct(
        private ?SerializedSuiteEntity $entity,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->entity && $this->requests->isEmpty();
    }

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false, false);
        }

        if (null === $this->entity) {
            return new MetaState(false, false, true);
        }

        return $this->entity->getMetaState();
    }

    /**
     * @return SerializedSerializedSuite
     */
    public function toArray(): array
    {
        $state = $this->entity?->getState();
        if ($this->preparation->hasFailure()) {
            $state = 'failed';
        }

        return [
            'state' => $state,
            'is_prepared' => $this->entity?->isPrepared() ?? false,
            'meta_state' => $this->getMetaState()->jsonSerialize(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => $this->preparation->jsonSerialize(),
        ];
    }
}
