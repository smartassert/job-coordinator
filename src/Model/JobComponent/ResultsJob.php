<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\ResultsJob as ResultsJobEntity;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedRemoteRequestCollectionGroup from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 *
 * @phpstan-type SerializedResultsJob array{
 *   state: ?string,
 *   end_state: ?string,
 *   meta_state: SerializedMetaState,
 *   requests: SerializedRemoteRequestCollectionGroup,
 *   preparation: SerializedPreparation,
 *   has_events: bool,
 * }
 */
readonly class ResultsJob implements SerializeToArrayInterface
{
    public function __construct(
        private ?ResultsJobEntity $entity,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

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
     * @return SerializedResultsJob
     */
    public function toArray(): array
    {
        $state = $this->entity?->getState();
        $endState = $this->entity?->getEndState();

        if ($this->preparation->hasFailure()) {
            $state = 'ended';
            $endState = 'failed';
        }

        return [
            'state' => $state,
            'end_state' => $endState,
            'meta_state' => $this->getMetaState()->toArray(),
            'requests' => $this->requests->toArray(),
            'preparation' => $this->preparation->toArray(),
            'has_events' => $this->entity?->hasEvents() ?? false,
        ];
    }
}
