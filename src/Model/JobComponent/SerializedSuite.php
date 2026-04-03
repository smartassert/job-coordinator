<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\SerializedSuite as SerializedSuiteEntity;
use App\Enum\JobComponentName;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 *
 * @phpstan-type SerializedSerializedSuite array{
 *   state: ?string,
 *   is_prepared: bool,
 *   meta_state: MetaState,
 *   requests: SerializedRemoteRequestCollection,
 *   preparation: SerializedPreparation
 * }
 */
readonly class SerializedSuite implements SerializeToArrayInterface, JobComponentInterface
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

    public function getName(): JobComponentName
    {
        return JobComponentName::SERIALIZED_SUITE;
    }

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false);
        }

        if (null === $this->entity) {
            return new MetaState(false, false);
        }

        return $this->entity->getMetaState();
    }

    /**
     * @return ?SerializedSerializedSuite
     */
    public function jsonSerialize(): ?array
    {
        if (null === $this->entity && $this->requests->isEmpty()) {
            return null;
        }

        return [
            'state' => $this->entity?->getState() ?? null,
            'is_prepared' => $this->entity?->isPrepared() ?? false,
            'meta_state' => $this->getMetaState(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => $this->preparation->jsonSerialize(),
        ];
    }
}
